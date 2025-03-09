<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;

/**
 * Dependency processing tool class, including processing extensions, library dependency list order, etc.
 */
class DependencyUtil
{
    /**
     * Convert platform extensions to library dependencies and suggestions.
     *
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public static function platExtToLibs(): array
    {
        $exts = Config::getExts();
        $libs = Config::getLibs();
        $dep_list = [];
        foreach ($exts as $ext_name => $ext) {
            // convert ext-depends value to ext@xxx
            $ext_depends = Config::getExt($ext_name, 'ext-depends', []);
            $ext_depends = array_map(fn ($x) => "ext@{$x}", $ext_depends);
            // convert ext-suggests value to ext@xxx
            $ext_suggests = Config::getExt($ext_name, 'ext-suggests', []);
            $ext_suggests = array_map(fn ($x) => "ext@{$x}", $ext_suggests);
            // merge ext-depends with lib-depends
            $lib_depends = Config::getExt($ext_name, 'lib-depends', []);
            $depends = array_merge($ext_depends, $lib_depends, ['php']);
            // merge ext-suggests with lib-suggests
            $lib_suggests = Config::getExt($ext_name, 'lib-suggests', []);
            $suggests = array_merge($ext_suggests, $lib_suggests);
            $dep_list["ext@{$ext_name}"] = [
                'depends' => $depends,
                'suggests' => $suggests,
            ];
        }
        foreach ($libs as $lib_name => $lib) {
            $dep_list[$lib_name] = [
                'depends' => array_merge(Config::getLib($lib_name, 'lib-depends', []), ['lib-base']),
                'suggests' => Config::getLib($lib_name, 'lib-suggests', []),
            ];
        }
        // here is an array that only contains dependency map
        return $dep_list;
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public static function getLibs(array $libs, bool $include_suggested_libs = false): array
    {
        $dep_list = self::platExtToLibs();

        if ($include_suggested_libs) {
            foreach ($dep_list as $name => $obj) {
                $del_list = [];
                foreach ($obj['suggests'] as $id => $suggest) {
                    if (!str_starts_with($suggest, 'ext@')) {
                        $dep_list[$name]['depends'][] = $suggest;
                        $del_list[] = $id;
                    }
                }
                foreach ($del_list as $id) {
                    unset($dep_list[$name]['suggests'][$id]);
                }
                $dep_list[$name]['suggests'] = array_values($dep_list[$name]['suggests']);
            }
        }

        $final = self::doVisitPlat($libs, $dep_list);

        $libs_final = [];
        foreach ($final as $item) {
            if (!str_starts_with($item, 'ext@')) {
                $libs_final[] = $item;
            }
        }
        return $libs_final;
    }

    /**
     * @throws FileSystemException|WrongUsageException
     */
    public static function getExtsAndLibs(array $exts, array $additional_libs = [], bool $include_suggested_exts = false, bool $include_suggested_libs = false): array
    {
        $dep_list = self::platExtToLibs();

        // include suggested extensions
        if ($include_suggested_exts) {
            // check every deps suggests contains ext@
            foreach ($dep_list as $name => $obj) {
                $del_list = [];
                foreach ($obj['suggests'] as $id => $suggest) {
                    if (str_starts_with($suggest, 'ext@')) {
                        $dep_list[$name]['depends'][] = $suggest;
                        $del_list[] = $id;
                    }
                }
                foreach ($del_list as $id) {
                    unset($dep_list[$name]['suggests'][$id]);
                }
                $dep_list[$name]['suggests'] = array_values($dep_list[$name]['suggests']);
            }
        }

        // include suggested libraries
        if ($include_suggested_libs) {
            // check every deps suggests
            foreach ($dep_list as $name => $obj) {
                $del_list = [];
                foreach ($obj['suggests'] as $id => $suggest) {
                    if (!str_starts_with($suggest, 'ext@')) {
                        $dep_list[$name]['depends'][] = $suggest;
                        $del_list[] = $id;
                    }
                }
                foreach ($del_list as $id) {
                    unset($dep_list[$name]['suggests'][$id]);
                }
                $dep_list[$name]['suggests'] = array_values($dep_list[$name]['suggests']);
            }
        }

        // convert ext_name to ext@ext_name
        $origin_exts = $exts;
        $exts = array_map(fn ($x) => "ext@{$x}", $exts);
        $exts = array_merge($exts, $additional_libs);

        $final = self::doVisitPlat($exts, $dep_list);

        // revert array
        $exts_final = [];
        $libs_final = [];
        $not_included_final = [];
        foreach ($final as $item) {
            if (str_starts_with($item, 'ext@')) {
                $tmp = substr($item, 4);
                if (!in_array($tmp, $origin_exts)) {
                    $not_included_final[] = $tmp;
                }
                $exts_final[] = $tmp;
            } else {
                $libs_final[] = $item;
            }
        }
        return [$exts_final, $libs_final, $not_included_final];
    }

    /**
     * @throws WrongUsageException
     */
    private static function doVisitPlat(array $deps, array $dep_list): array
    {
        // default: get extension exts and libs sorted by dep_list
        $sorted = [];
        $visited = [];
        foreach ($deps as $ext_name) {
            if (!isset($dep_list[$ext_name])) {
                $ext_name = str_starts_with($ext_name, 'ext@') ? ('Extension [' . substr($ext_name, 4) . ']') : ('Library [' . $ext_name . ']');
                throw new WrongUsageException("{$ext_name} not exist !");
            }
            if (!isset($visited[$ext_name])) {
                self::visitPlatDeps($ext_name, $dep_list, $visited, $sorted);
            }
        }
        $sorted_suggests = [];
        $visited_suggests = [];
        $final = [];
        foreach ($deps as $ext_name) {
            if (!isset($visited_suggests[$ext_name])) {
                self::visitPlatAllDeps($ext_name, $dep_list, $visited_suggests, $sorted_suggests);
            }
        }
        foreach ($sorted_suggests as $suggest) {
            if (in_array($suggest, $sorted)) {
                $final[] = $suggest;
            }
        }
        return $final;
    }

    private static function visitPlatAllDeps(string $lib_name, array $dep_list, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$lib_name])) {
            return;
        }
        $visited[$lib_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        foreach (array_merge($dep_list[$lib_name]['depends'], $dep_list[$lib_name]['suggests']) as $dep) {
            self::visitPlatAllDeps($dep, $dep_list, $visited, $sorted);
        }
        $sorted[] = $lib_name;
    }

    private static function visitPlatDeps(string $lib_name, array $dep_list, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$lib_name])) {
            return;
        }
        $visited[$lib_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        if (!isset($dep_list[$lib_name])) {
            throw new WrongUsageException("{$lib_name} not exist !");
        }
        foreach ($dep_list[$lib_name]['depends'] as $dep) {
            self::visitPlatDeps($dep, $dep_list, $visited, $sorted);
        }
        $sorted[] = $lib_name;
    }
}
