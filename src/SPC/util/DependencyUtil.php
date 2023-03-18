<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\Config;

/**
 * 依赖处理工具类，包含处理扩展、库的依赖列表顺序等
 */
class DependencyUtil
{
    /**
     * 根据需要的 ext 列表获取依赖的 lib 列表，同时根据依赖关系排序
     *
     * @param  array               $exts            要获取 libs 依赖的列表
     * @param  array               $additional_libs 额外要添加的库列表，用于激活 lib-suggests 触发的额外库特性
     * @return array               返回一个包含三个数组的数组，第一个是排序后的 ext 列表，第二个是排序后的 lib 列表，第三个是没有传入但是依赖了的 ext 列表
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function getExtLibsByDeps(array $exts, array $additional_libs = []): array
    {
        // 先对扩展列表进行一个依赖筛选
        $sorted = [];
        $visited = [];
        $not_included_exts = [];
        foreach ($exts as $ext) {
            if (!isset($visited[$ext])) {
                self::visitExtDeps($ext, $visited, $sorted);
            }
        }
        $libs = $additional_libs;
        // 遍历每一个 ext 的 libs
        foreach ($sorted as $ext) {
            if (!in_array($ext, $exts)) {
                $not_included_exts[] = $ext;
            }
            foreach (Config::getExt($ext, 'lib-depends', []) as $lib) {
                if (!in_array($lib, $libs)) {
                    $libs[] = $lib;
                }
            }
        }
        return [$sorted, self::getLibsByDeps($libs), $not_included_exts];
    }

    /**
     * 根据 lib 库的依赖关系进行一个排序，同时返回多出来的依赖列表
     *
     * @param  array               $libs 要排序的 libs 列表
     * @return array               排序后的列表
     * @throws FileSystemException
     * @throws RuntimeException
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
        return $sorted;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
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
