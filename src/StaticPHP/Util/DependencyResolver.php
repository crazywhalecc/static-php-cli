<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\Package;

class DependencyResolver
{
    /**
     * Resolve dependencies for given packages.
     * It will return an array of package names in the order they should be built/installed.
     *
     * @param  Package[]|string[]           $packages             Package instance
     * @param  array<string, string[]>      $dependency_overrides Override dependencies (e.g. ['php' => ['ext-gd', 'ext-curl']])
     * @param  bool                         $include_suggests     Include suggested packages in the resolution
     * @param  null|array<string, string[]> &$why                 If provided, will be filled with a reverse dependency map
     * @return array<string>                Resolved package names in order
     */
    public static function resolve(array $packages, array $dependency_overrides = [], bool $include_suggests = false, ?array &$why = null): array
    {
        $dep_list = PackageConfig::getAll();
        $dep_list_clean = [];
        // clear array for further step
        foreach ($dep_list as $k => $v) {
            $dep_list_clean[$k] = [
                'depends' => PackageConfig::get($k, 'depends', []),
                'suggests' => PackageConfig::get($k, 'suggests', []),
            ];
        }

        // apply dependency overrides
        foreach ($dependency_overrides as $target_name => $deps) {
            $dep_list_clean[$target_name]['depends'] = array_merge($dep_list_clean[$target_name]['depends'] ?? [], $deps);
        }

        // mark suggests as depends
        if ($include_suggests) {
            foreach ($dep_list_clean as $pkg_name => $pkg_item) {
                $dep_list_clean[$pkg_name]['depends'] = array_merge($pkg_item['depends'], $pkg_item['suggests']);
                $dep_list_clean[$pkg_name]['suggests'] = [];
            }
        }

        $resolved = self::doVisitPlat($packages, $dep_list_clean);

        // Build reverse dependency map if $why is requested
        if ($why !== null) {
            $why = self::buildReverseDependencyMap($resolved, $dep_list_clean, $include_suggests);
        }

        return $resolved;
    }

    /**
     * Build a reverse dependency map for the resolved packages.
     * For each package that is depended upon, list which packages depend on it.
     *
     * @param  array<string>                                               $resolved         Resolved package names
     * @param  array<string, array{depends: string[], suggests: string[]}> $dep_list         Dependency declaration list
     * @param  bool                                                        $include_suggests Whether suggests are treated as depends
     * @return array<string, string[]>                                     Reverse dependency map [depended_pkg => [dependant1, dependant2, ...]]
     */
    private static function buildReverseDependencyMap(array $resolved, array $dep_list, bool $include_suggests): array
    {
        $why = [];
        $resolved_set = array_flip($resolved);

        foreach ($resolved as $pkg_name) {
            $deps = $dep_list[$pkg_name]['depends'] ?? [];
            if ($include_suggests) {
                $deps = array_merge($deps, $dep_list[$pkg_name]['suggests'] ?? []);
            }

            foreach ($deps as $dep) {
                // Only include dependencies that are in the resolved set
                if (isset($resolved_set[$dep])) {
                    if (!isset($why[$dep])) {
                        $why[$dep] = [];
                    }
                    $why[$dep][] = $pkg_name;
                }
            }
        }

        return $why;
    }

    /**
     * Visitor pattern implementation for dependency resolution.
     *
     * @param  Package[]|string[]                                          $packages Packages list (input)
     * @param  array<string, array{depends: string[], suggests: string[]}> $dep_list Dependency declaration list
     * @return array                                                       Resolved packages array
     */
    private static function doVisitPlat(array $packages, array $dep_list): array
    {
        $sorted = [];
        $visited = [];
        foreach ($packages as $pkg) {
            $pkg_name = is_string($pkg) ? $pkg : $pkg->getName();
            if (!isset($dep_list[$pkg_name])) {
                throw new WrongUsageException("Package '{$pkg_name}' does not exist in config, please check your package name !");
            }
            if (!isset($visited[$pkg_name])) {
                self::visitPlatDeps($pkg_name, $dep_list, $visited, $sorted);
            }
        }

        $sorted_suggests = [];
        $visited_suggests = [];
        $final = [];
        foreach ($packages as $pkg) {
            $pkg_name = is_string($pkg) ? $pkg : $pkg->getName();
            if (!isset($visited_suggests[$pkg_name])) {
                self::visitPlatAllDeps($pkg_name, $dep_list, $visited_suggests, $sorted_suggests);
            }
        }
        foreach ($sorted_suggests as $suggest) {
            if (in_array($suggest, $sorted)) {
                $final[] = $suggest;
            }
        }
        return $final;
    }

    private static function visitPlatAllDeps(string $pkg_name, array $dep_list, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$pkg_name])) {
            return;
        }
        $visited[$pkg_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        foreach (array_merge($dep_list[$pkg_name]['depends'], $dep_list[$pkg_name]['suggests']) as $dep) {
            self::visitPlatAllDeps($dep, $dep_list, $visited, $sorted);
        }
        $sorted[] = $pkg_name;
    }

    private static function visitPlatDeps(string $pkg_name, array $dep_list, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$pkg_name])) {
            return;
        }
        $visited[$pkg_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        if (!isset($dep_list[$pkg_name])) {
            throw new WrongUsageException("{$pkg_name} not exist !");
        }
        foreach ($dep_list[$pkg_name]['depends'] as $dep) {
            self::visitPlatDeps($dep, $dep_list, $visited, $sorted);
        }
        $sorted[] = $pkg_name;
    }
}
