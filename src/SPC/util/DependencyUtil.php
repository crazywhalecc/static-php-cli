<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;

/**
 * 依赖处理工具类，包含处理扩展、库的依赖列表顺序等
 */
class DependencyUtil
{
    public static function getExtsAndLibs(array $exts, array $additional_libs = [], bool $include_suggested_exts = false, bool $include_suggested_libs = false): array
    {
        if (!$include_suggested_exts && !$include_suggested_libs) {
            return self::getExtLibsByDeps($exts, $additional_libs);
        }
        if ($include_suggested_exts && $include_suggested_libs) {
            return self::getAllExtLibsByDeps($exts, $additional_libs);
        }
        if (!$include_suggested_exts) {
            return self::getExtLibsByDeps($exts, $additional_libs);
        }
        return self::getAllExtLibsByDeps($exts, $additional_libs, false);
    }

    /**
     * Obtain the dependent lib list according to the required ext list, and sort according to the dependency
     *
     * @param  array               $exts            extensions list
     * @param  array               $additional_libs List of additional libraries to add to activate the extra library features triggered by lib-suggests
     * @return array               Returns an array containing three arrays, [extensions, libraries, not included extensions]
     * @throws WrongUsageException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public static function getExtLibsByDeps(array $exts, array $additional_libs = [], bool $include_suggested_exts = false): array
    {
        $sorted = [];
        $visited = [];
        $not_included_exts = [];
        foreach ($exts as $ext) {
            if (!isset($visited[$ext])) {
                self::visitExtDeps($ext, $visited, $sorted);
            }
        }
        $sorted_suggests = [];
        $visited_suggests = [];
        $final = [];
        foreach ($exts as $ext) {
            if (!isset($visited_suggests[$ext])) {
                self::visitExtAllDeps($ext, $visited_suggests, $sorted_suggests);
            }
        }
        foreach ($sorted_suggests as $suggest) {
            if (in_array($suggest, $sorted)) {
                $final[] = $suggest;
            }
        }
        $libs = $additional_libs;

        foreach ($final as $ext) {
            if (!in_array($ext, $exts)) {
                $not_included_exts[] = $ext;
            }
            foreach (Config::getExt($ext, 'lib-depends', []) as $lib) {
                if (!in_array($lib, $libs)) {
                    $libs[] = $lib;
                }
            }
        }
        return [$final, self::getLibsByDeps($libs), $not_included_exts];
    }

    /**
     * 根据 lib 库的依赖关系进行一个排序，同时返回多出来的依赖列表
     *
     * @param  array               $libs 要排序的 libs 列表
     * @return array               排序后的列表
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function getLibsByDeps(array $libs): array
    {
        $sorted = [];
        $visited = [];

        // 遍历所有
        foreach ($libs as $lib) {
            if (!isset($visited[$lib])) {
                self::visitLibDeps($lib, $visited, $sorted);
            }
        }

        $sorted_suggests = [];
        $visited_suggests = [];
        $final = [];
        foreach ($libs as $lib) {
            if (!isset($visited_suggests[$lib])) {
                self::visitLibAllDeps($lib, $visited_suggests, $sorted_suggests);
            }
        }
        foreach ($sorted_suggests as $suggest) {
            if (in_array($suggest, $sorted)) {
                $final[] = $suggest;
            }
        }
        return $final;
    }

    public static function getAllExtLibsByDeps(array $exts, array $additional_libs = [], bool $include_suggested_libs = true): array
    {
        $sorted = [];
        $visited = [];
        $not_included_exts = [];
        foreach ($exts as $ext) {
            if (!isset($visited[$ext])) {
                self::visitExtAllDeps($ext, $visited, $sorted);
            }
        }
        $libs = $additional_libs;
        foreach ($sorted as $ext) {
            if (!in_array($ext, $exts)) {
                $not_included_exts[] = $ext;
            }
            $total = $include_suggested_libs ? array_merge(Config::getExt($ext, 'lib-depends', []), Config::getExt($ext, 'lib-suggests', [])) : Config::getExt($ext, 'lib-depends', []);
            foreach ($total as $dep) {
                if (!in_array($dep, $libs)) {
                    $libs[] = $dep;
                }
            }
        }
        return [$sorted, self::getAllLibsByDeps($libs), $not_included_exts];
    }

    public static function getAllLibsByDeps(array $libs): array
    {
        $sorted = [];
        $visited = [];

        foreach ($libs as $lib) {
            if (!isset($visited[$lib])) {
                self::visitLibAllDeps($lib, $visited, $sorted);
            }
        }
        return $sorted;
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    private static function visitLibAllDeps(string $lib_name, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$lib_name])) {
            return;
        }
        $visited[$lib_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        foreach (array_merge(Config::getLib($lib_name, 'lib-depends', []), Config::getLib($lib_name, 'lib-suggests', [])) as $dep) {
            self::visitLibDeps($dep, $visited, $sorted);
        }
        $sorted[] = $lib_name;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private static function visitExtAllDeps(string $ext_name, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$ext_name])) {
            return;
        }
        $visited[$ext_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        foreach (array_merge(Config::getExt($ext_name, 'ext-depends', []), Config::getExt($ext_name, 'ext-suggests', [])) as $dep) {
            self::visitExtDeps($dep, $visited, $sorted);
        }
        $sorted[] = $ext_name;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private static function visitLibDeps(string $lib_name, array &$visited, array &$sorted): void
    {
        // 如果已经识别到了，那就不管
        if (isset($visited[$lib_name])) {
            return;
        }
        $visited[$lib_name] = true;
        // 遍历该依赖的所有依赖（此处的 getLib 如果检测到当前库不存在的话，会抛出异常）
        foreach (Config::getLib($lib_name, 'lib-depends', []) as $dep) {
            self::visitLibDeps($dep, $visited, $sorted);
        }
        $sorted[] = $lib_name;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    private static function visitExtDeps(string $ext_name, array &$visited, array &$sorted): void
    {
        if (isset($visited[$ext_name])) {
            return;
        }
        $visited[$ext_name] = true;
        foreach (Config::getExt($ext_name, 'ext-depends', []) as $dep) {
            self::visitExtDeps($dep, $visited, $sorted);
        }
        $sorted[] = $ext_name;
    }
}
