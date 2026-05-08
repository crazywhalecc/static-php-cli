<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Config\PackageConfig;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-ext-test-matrix', 'Generate GitHub Actions extension test matrix JSON', [], true)]
class GenExtTestMatrixCommand extends BaseCommand
{
    private const string BUILD_TARGETS = '--build-cli --build-cgi --build-micro';

    private const array OS_RUNNERS = [
        'linux' => ['arch' => 'x86_64', 'runner' => 'ubuntu-latest', 'os_key' => 'Linux'],
        'windows' => ['arch' => 'x86_64', 'runner' => 'windows-latest', 'os_key' => 'Windows'],
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

    protected bool $no_motd = true;

    public function handle(): int
    {
        if (!spc_mode(SPC_MODE_SOURCE)) {
            $this->output->writeln('<error>This command is only available in source mode.</error>');
            return static::USER_ERROR;
        }

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

        $entries = [];

        foreach (self::OS_RUNNERS as $os => $os_info) {
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
            foreach (array_merge($os_regular, $os_virtual) as $pkg_name => $config) {
                $raw = array_merge(
                    $this->resolvePlatformList($config, 'depends', $os),
                    $this->resolvePlatformList($config, 'suggests', $os),
                );
                $ext_deps[$pkg_name] = array_values(array_filter(
                    $raw,
                    fn ($d) => isset($pool_set[$d]) && $d !== $pkg_name
                ));
            }

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

            foreach (array_merge($roots, $non_roots) as $ext) {
                if (isset($covered[$ext])) {
                    continue;
                }
                $chain = $this->dfsCollect($ext, $ext_deps, $pool_set, $covered);
                if (count($chain) === 1 && empty($ext_deps[$ext])) {
                    $orphans[] = $this->displayName($ext);
                } else {
                    $groups[] = implode(',', array_map($this->displayName(...), $chain));
                }
            }

            // All orphans become a single batched matrix entry
            if (!empty($orphans)) {
                sort($orphans);
                $groups[] = implode(',', $orphans);
            }

            sort($groups);
            foreach ($groups as $group) {
                $extra = $this->extraBuildFlags($group);
                $entries[] = [
                    'runner' => $os_info['runner'],
                    'os' => $os,
                    'arch' => $os_info['arch'],
                    'extension' => $group,
                    'build-args' => '"' . $group . '" ' . self::BUILD_TARGETS . ($extra !== '' ? ' ' . $extra : ''),
                ];
            }
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
