<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\PackageConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('dev:gen-ext-test-matrix', 'Generate GitHub Actions extension test matrix JSON', [], true)]
class GenExtTestMatrixCommand extends BaseCommand
{
    private const string BUILD_TARGETS = '--build-cli --build-cgi --build-micro --with-suggests -vvv';

    private const array OS_RUNNERS = [
        'linux' => ['arch' => 'x86_64', 'runner' => 'ubuntu-latest', 'os_key' => 'Linux'],
        'windows' => ['arch' => 'x86_64', 'runner' => 'windows-latest', 'os_key' => 'Windows'],
        'macos' => ['arch' => 'aarch64', 'runner' => 'macos-15', 'os_key' => 'Darwin'],
    ];

    /**
     * Tier 2 runners: Linux aarch64 + macOS x86_64, no Windows.
     */
    private const array OS_RUNNERS_TIER2 = [
        'linux' => ['arch' => 'aarch64', 'runner' => 'ubuntu-24.04-arm', 'os_key' => 'Linux'],
        'macos' => ['arch' => 'x86_64', 'runner' => 'macos-15-intel', 'os_key' => 'Darwin'],
    ];

    /**
     * Extensions excluded from specific OS matrix entries.
     */
    private const array OS_EXCLUDE = [
        'linux' => ['glfw'],
    ];

    /**
     * Extra build flags appended when a matrix entry contains any of the listed extensions.
     * Key: extension display name (without ext- prefix). Value: extra flags string.
     */
    private const array EXTRA_BUILD_FLAGS = [
        'parallel' => '--enable-zts',
    ];

    /**
     * Pairs of extensions that cannot be built together in the same matrix entry.
     */
    private const array CONFLICTS = [
        ['grpc', 'protobuf'],
        ['swow', 'swoole'],
    ];

    /**
     * Extensions that must always appear alone in their own matrix entry.
     * Use display names (without ext- prefix).
     */
    private const array STANDALONE = [
        'grpc',
        'glfw',
        'imagick',
        'intl',
        'mongodb',
        'gmssl',
    ];

    /**
     * Extensions that are emitted as isolated standalone entries.
     */
    private const array STANDALONE_ISOLATED = [
        'swow' => '',
        'swoole' => 'swoole-hook-',
    ];

    /**
     * Maximum number of orphan extensions per matrix entry.
     */
    private const int ORPHAN_BATCH_SIZE = 15;

    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addOption('for-extensions', null, InputOption::VALUE_OPTIONAL, 'Filter by extension display names, comma-separated', '')
            ->addOption('for-libs', null, InputOption::VALUE_OPTIONAL, 'Filter by lib names (depends+suggests), comma-separated', '')
            ->addOption('os', null, InputOption::VALUE_OPTIONAL, 'Filter by OS (Linux/Darwin/Windows), comma-separated', '')
            ->addOption('tier2', null, InputOption::VALUE_NONE, 'Use Tier 2 runners (Linux aarch64 + macOS x86_64, no Windows)');
    }

    public function handle(): int
    {
        if (!spc_mode(SPC_MODE_SOURCE)) {
            $this->output->writeln('<error>This command is only available in source mode.</error>');
            return static::USER_ERROR;
        }

        $parse_option = fn (string $name): array => array_values(array_filter(array_map('trim', explode(',', (string) $this->input->getOption($name)))));
        $filter_extensions = $parse_option('for-extensions');
        $filter_libs = $parse_option('for-libs');
        $filter_os_keys = $parse_option('os');
        $tier2 = (bool) $this->input->getOption('tier2');

        $base_runners = $tier2 ? self::OS_RUNNERS_TIER2 : self::OS_RUNNERS;

        $all = PackageConfig::getAll();

        // Separate into regular and virtual extensions (build-static:false excluded globally)
        $all_regular = [];
        $all_virtual = [];
        foreach ($all as $pkg_name => $config) {
            if (($config['type'] ?? '') !== 'php-extension') {
                continue;
            }
            if (($config['php-extension']['build-static'] ?? null) === false) {
                continue;
            }
            if (($config['php-extension']['arg-type'] ?? '') === 'none') {
                $all_virtual[$pkg_name] = $config;
            } else {
                $all_regular[$pkg_name] = $config;
            }
        }

        $os_runners = empty($filter_os_keys)
            ? $base_runners
            : array_filter($base_runners, fn ($info) => in_array($info['os_key'], $filter_os_keys, true));

        $entries = [];
        $all_ext_lib_deps = [];

        foreach ($os_runners as $os => $os_info) {
            $os_key = $os_info['os_key'];

            // Filter by OS support
            $os_exclude = array_fill_keys(array_map(fn ($n) => 'ext-' . $n, self::OS_EXCLUDE[$os] ?? []), true);
            $os_regular = array_filter($all_regular, fn ($c, $k) => $this->supportsOS($c, $os_key) && !isset($os_exclude[$k]), ARRAY_FILTER_USE_BOTH);
            $os_virtual = array_filter($all_virtual, fn ($c, $k) => $this->supportsOS($c, $os_key) && !isset($os_exclude[$k]), ARRAY_FILTER_USE_BOTH);

            // Pool: all ext-* names available on this OS (regular + virtual)
            $pool_set = array_fill_keys(
                array_merge(array_keys($os_regular), array_keys($os_virtual)),
                true
            );

            // Compute ext_deps for every pool member: union of depends + suggests, limited to pool
            $ext_deps = [];
            $os_lib_deps = [];
            foreach (array_merge($os_regular, $os_virtual) as $pkg_name => $config) {
                $raw = array_merge(
                    $this->resolvePlatformList($config, 'depends', $os),
                    $this->resolvePlatformList($config, 'suggests', $os),
                );
                $ext_deps[$pkg_name] = array_values(array_filter(
                    $raw,
                    fn ($d) => isset($pool_set[$d]) && $d !== $pkg_name
                ));
                $os_lib_deps[$this->displayName($pkg_name)] = array_values(array_filter(
                    $raw,
                    fn ($d) => !str_starts_with($d, 'ext-')
                ));
            }
            $all_ext_lib_deps[$os] = $os_lib_deps;

            // Which regular exts are reachable as a dep/suggest from another regular ext?
            $depended_on = [];
            foreach ($os_regular as $pkg_name => $_) {
                foreach ($ext_deps[$pkg_name] as $dep) {
                    $depended_on[$dep] = true;
                }
            }

            // Process order: roots (not depended on) first, then non-roots; each group alpha-sorted
            $roots = [];
            $non_roots = [];
            foreach (array_keys($os_regular) as $pkg_name) {
                if (isset($depended_on[$pkg_name])) {
                    $non_roots[] = $pkg_name;
                } else {
                    $roots[] = $pkg_name;
                }
            }
            sort($roots);
            sort($non_roots);

            // DFS to collect dependency chains; true orphans (no ext-* relations) are batched
            $covered = [];
            $groups = [];
            $orphans = [];
            $standalone_set = array_fill_keys(self::STANDALONE, true);
            $standalone_isolated = self::STANDALONE_ISOLATED;

            foreach (array_merge($roots, $non_roots) as $ext) {
                if (isset($covered[$ext])) {
                    continue;
                }
                $display = $this->displayName($ext);

                if (array_key_exists($display, $standalone_isolated)) {
                    // Isolated standalone: mark only this ext + its hook virtuals as covered
                    $covered[$ext] = true;
                    $hook_prefix = $standalone_isolated[$display];
                    $group_names = [$display];
                    if ($hook_prefix !== '') {
                        foreach ($os_virtual as $vpkg => $_) {
                            $vdisplay = $this->displayName($vpkg);
                            if (str_starts_with($vdisplay, $hook_prefix) && !isset($covered[$vpkg])) {
                                $covered[$vpkg] = true;
                                $group_names[] = $vdisplay;
                            }
                        }
                        sort($group_names);
                    }
                    $groups[] = implode(',', $group_names);
                    continue;
                }

                $chain = $this->dfsCollect($ext, $ext_deps, $pool_set, $covered);
                if (isset($standalone_set[$display])) {
                    // Always emit standalone extensions as their own single entry
                    $groups[] = $display;
                } elseif (count($chain) === 1 && empty($ext_deps[$ext])) {
                    $orphans[] = $display;
                } else {
                    $groups[] = implode(',', array_map($this->displayName(...), $chain));
                }
            }

            // Batch orphans, splitting conflicting extensions into separate entries
            if (!empty($orphans)) {
                sort($orphans);
                foreach ($this->splitOrphansByConflicts($orphans) as $batch) {
                    $groups[] = implode(',', $batch);
                }
            }

            sort($groups);
            foreach ($groups as $group) {
                $extra = $this->extraBuildFlags($group);
                $entries[] = [
                    'runner' => $os_info['runner'],
                    'os' => $os,
                    'arch' => $os_info['arch'],
                    'extension' => $group,
                    'build-args' => './bin/spc build "' . $group . '" ' . self::BUILD_TARGETS . ($extra !== '' ? ' ' . $extra : ''),
                ];
            }
        }

        if (!empty($filter_extensions)) {
            $entries = array_values(array_filter($entries, function (array $entry) use ($filter_extensions): bool {
                $names = explode(',', $entry['extension']);
                return count(array_intersect($names, $filter_extensions)) > 0;
            }));
        }

        if (!empty($filter_libs)) {
            $entries = array_values(array_filter($entries, function (array $entry) use ($filter_libs, $all_ext_lib_deps): bool {
                $names = explode(',', $entry['extension']);
                $lib_deps = $all_ext_lib_deps[$entry['os']] ?? [];
                foreach ($names as $name) {
                    if (count(array_intersect($lib_deps[$name] ?? [], $filter_libs)) > 0) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $this->output->write(json_encode($entries, JSON_UNESCAPED_SLASHES));
        return static::SUCCESS;
    }

    /**
     * DFS-collect the dependency chain starting from $ext.
     * Marks all visited nodes in $covered to prevent duplicates and handle cycles.
     */
    private function dfsCollect(string $ext, array $ext_deps, array $pool_set, array &$covered): array
    {
        if (isset($covered[$ext])) {
            return [];
        }
        $covered[$ext] = true;
        $chain = [$ext];
        foreach ($ext_deps[$ext] ?? [] as $dep) {
            if (!isset($covered[$dep]) && isset($pool_set[$dep])) {
                $chain = array_merge($chain, $this->dfsCollect($dep, $ext_deps, $pool_set, $covered));
            }
        }
        return $chain;
    }

    private function supportsOS(array $config, string $os_key): bool
    {
        $os_list = $config['php-extension']['os'] ?? null;
        return $os_list === null || in_array($os_key, $os_list, true);
    }

    private function displayName(string $pkg_name): string
    {
        return str_starts_with($pkg_name, 'ext-') ? substr($pkg_name, 4) : $pkg_name;
    }

    /**
     * Split orphans into batches such that no two conflicting extensions share a batch.
     * Uses a greedy graph-coloring approach.
     *
     * @param  string[]   $orphans display names, pre-sorted
     * @return string[][] array of batches, each batch is an array of display names
     */
    private function splitOrphansByConflicts(array $orphans): array
    {
        $adjacency = [];
        foreach (self::CONFLICTS as [$a, $b]) {
            $adjacency[$a][$b] = true;
            $adjacency[$b][$a] = true;
        }

        $batches = [];
        foreach ($orphans as $ext) {
            $placed = false;
            foreach ($batches as &$batch) {
                if (count($batch) >= self::ORPHAN_BATCH_SIZE) {
                    continue;
                }
                $conflict = false;
                foreach ($batch as $member) {
                    if (isset($adjacency[$ext][$member])) {
                        $conflict = true;
                        break;
                    }
                }
                if (!$conflict) {
                    $batch[] = $ext;
                    $placed = true;
                    break;
                }
            }
            unset($batch);
            if (!$placed) {
                $batches[] = [$ext];
            }
        }
        return $batches;
    }

    /**
     * Returns any extra build flags required for an extension group string.
     * Checks whether any extension in the comma-separated group matches EXTRA_BUILD_FLAGS.
     */
    private function extraBuildFlags(string $group): string
    {
        $names = explode(',', $group);
        $flags = [];
        foreach (self::EXTRA_BUILD_FLAGS as $ext => $extra) {
            if (in_array($ext, $names, true)) {
                $flags[] = $extra;
            }
        }
        return implode(' ', $flags);
    }

    /**
     * Resolve the value of a platform-specific array field, applying the suffix fallback chain.
     *
     * Fallback rules (same as PackageConfig::get):
     *   linux   : @linux  → @unix  → (base)
     *   macos   : @macos  → @unix  → (base)
     *   windows : @windows         → (base)
     */
    private function resolvePlatformList(array $config, string $field, string $platform): array
    {
        return match ($platform) {
            'linux' => $config["{$field}@linux"] ?? $config["{$field}@unix"] ?? $config[$field] ?? [],
            'macos' => $config["{$field}@macos"] ?? $config["{$field}@unix"] ?? $config[$field] ?? [],
            'windows' => $config["{$field}@windows"] ?? $config[$field] ?? [],
            default => $config[$field] ?? [],
        };
    }
}
