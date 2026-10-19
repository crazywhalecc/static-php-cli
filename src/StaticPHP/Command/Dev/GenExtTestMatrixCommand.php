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

    private const string FRANKENPHP_BUILD_TARGETS = '--build-cli --build-frankenphp --enable-zts --with-suggests -vvv';

    private const array SUPPORTED_SAPIS = [
        'frankenphp',
    ];

    private const array OS_RUNNERS = [
        'linux' => ['arch' => 'x86_64', 'runner' => 'ubuntu-latest', 'os_key' => 'Linux'],
        'windows' => ['arch' => 'x86_64', 'runner' => 'windows-2025', 'os_key' => 'Windows'],
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
            ->addOption('sapi', null, InputOption::VALUE_OPTIONAL, 'Add extra SAPI build tests, comma-separated (supported: frankenphp)', '')
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
        $filter_sapis = array_unique($parse_option('sapi'));
        $tier2 = (bool) $this->input->getOption('tier2');

        $unknown_sapis = array_values(array_diff($filter_sapis, self::SUPPORTED_SAPIS));
        if (!empty($unknown_sapis)) {
            $this->output->writeln('<error>Unsupported SAPI(s): ' . implode(', ', $unknown_sapis) . '</error>');
            return static::USER_ERROR;
        }

        $base_runners = $tier2 ? self::OS_RUNNERS_TIER2 : self::OS_RUNNERS;

        $all = PackageConfig::getAll();

        // Separate into regular and virtual extensions (build-static:false excluded globally)
        $all_regular = [];
        $all_virtual = [];
        $all_libraries = [];
        foreach ($all as $pkg_name => $config) {
            if (($config['type'] ?? '') === 'library') {
                $all_libraries[$pkg_name] = $config;
                continue;
            }
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

        [$entries, $all_ext_lib_deps] = $this->buildEntriesForRunners(
            $base_runners,
            $filter_os_keys,
            $all_regular,
            $all_virtual,
            $all_libraries,
            self::BUILD_TARGETS,
            'default',
        );

        if (in_array('frankenphp', $filter_sapis, true)) {
            [$frankenphp_entries, $frankenphp_ext_lib_deps] = $this->buildEntriesForRunners(
                self::OS_RUNNERS,
                [],
                $all_regular,
                $all_virtual,
                $all_libraries,
                self::FRANKENPHP_BUILD_TARGETS,
                'frankenphp',
            );
            $entries = array_merge($entries, $frankenphp_entries);
            $all_ext_lib_deps = array_replace_recursive($all_ext_lib_deps, $frankenphp_ext_lib_deps);
        }

        if (!empty($filter_extensions) || !empty($filter_libs)) {
            $entries = array_values(array_filter($entries, function (array $entry) use ($filter_extensions, $filter_libs, $all_ext_lib_deps): bool {
                $names = explode(',', $entry['extension']);

                if (!empty($filter_extensions) && count(array_intersect($names, $filter_extensions)) > 0) {
                    return true;
                }

                if (!empty($filter_libs)) {
                    $lib_deps = $all_ext_lib_deps[$entry['os']] ?? [];
                    foreach ($names as $name) {
                        if (count(array_intersect($lib_deps[$name] ?? [], $filter_libs)) > 0) {
                            return true;
                        }
                    }
                }

                return false;
            }));
        }

        $this->output->write(json_encode($entries, JSON_UNESCAPED_SLASHES));
        return static::SUCCESS;
    }

    /**
     * @return array{array<int, array<string, string>>, array<string, array<string, string[]>>}
     */
    private function buildEntriesForRunners(
        array $base_runners,
        array $filter_os_keys,
        array $all_regular,
        array $all_virtual,
        array $all_libraries,
        string $build_targets,
        string $sapi,
    ): array {
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
                $os_lib_deps[$this->displayName($pkg_name)] = $this->collectLibraryDeps($raw, $all_libraries, $os);
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
                $entries[] = [
                    'runner' => $os_info['runner'],
                    'os' => $os,
                    'arch' => $os_info['arch'],
                    'sapi' => $sapi,
                    'extension' => $group,
                    'build-args' => './bin/spc build "' . $group . '" ' . $this->buildTargetsWithExtraFlags($build_targets, $group),
                ];
            }
        }

        return [$entries, $all_ext_lib_deps];
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
     * Collect direct and transitive library dependencies from a package dependency list.
     *
     * @param string[]               $deps
     * @param array<string, mixed[]> $library_configs
     * @param array<string, true>    $seen
     *
     * @return string[]
     */
    private function collectLibraryDeps(array $deps, array $library_configs, string $platform, array $seen = []): array
    {
        $collected = [];
        foreach ($deps as $dep) {
            if (str_starts_with($dep, 'ext-') || isset($seen[$dep])) {
                continue;
            }

            $seen[$dep] = true;
            $collected[$dep] = $dep;

            if (!isset($library_configs[$dep])) {
                continue;
            }

            $child_deps = array_merge(
                $this->resolvePlatformList($library_configs[$dep], 'depends', $platform),
                $this->resolvePlatformList($library_configs[$dep], 'suggests', $platform),
            );
            foreach ($this->collectLibraryDeps($child_deps, $library_configs, $platform, $seen) as $child_dep) {
                $collected[$child_dep] = $child_dep;
            }
        }
        return array_values($collected);
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

    private function buildTargetsWithExtraFlags(string $build_targets, string $group): string
    {
        $flags = explode(' ', $build_targets);
        foreach (explode(' ', $this->extraBuildFlags($group)) as $extra) {
            if ($extra === '' || in_array($extra, $flags, true)) {
                continue;
            }
            $flags[] = $extra;
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
