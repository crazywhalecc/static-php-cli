<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

/**
 * Unix 系统的工具函数 Trait，适用于 Linux、macOS
 */
trait UnixSystemUtilTrait
{
    /**
     * 生成 toolchain.cmake，用于 cmake 构建
     *
     * @param  string              $os          操作系统代号
     * @param  string              $target_arch 目标架构
     * @param  string              $cflags      CFLAGS 参数
     * @param  null|string         $cc          CC 参数（默认空）
     * @param  null|string         $cxx         CXX 参数（默认空）
     * @throws FileSystemException
     */
    public static function makeCmakeToolchainFile(
        string $os,
        string $target_arch,
        string $cflags,
        ?string $cc = null,
        ?string $cxx = null
    ): string {
        logger()->debug("making cmake tool chain file for {$os} {$target_arch} with CFLAGS='{$cflags}'");
        $root = BUILD_ROOT_PATH;
        $ccLine = '';
        if ($cc) {
            $ccLine = 'SET(CMAKE_C_COMPILER ' . $cc . ')';
        }
        $cxxLine = '';
        if ($cxx) {
            $cxxLine = 'SET(CMAKE_CXX_COMPILER ' . $cxx . ')';
        }
        $toolchain = <<<CMAKE
{$ccLine}
{$cxxLine}
SET(CMAKE_C_FLAGS "{$cflags}")
SET(CMAKE_CXX_FLAGS "{$cflags}")
SET(CMAKE_FIND_ROOT_PATH "{$root}")
SET(CMAKE_PREFIX_PATH "{$root}")
SET(CMAKE_INSTALL_PREFIX "{$root}")
SET(CMAKE_INSTALL_LIBDIR "lib")

set(PKG_CONFIG_EXECUTABLE "{$root}/bin/pkg-config")
set(CMAKE_FIND_ROOT_PATH_MODE_PROGRAM NEVER)
set(CMAKE_FIND_ROOT_PATH_MODE_LIBRARY ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_INCLUDE ONLY)
set(CMAKE_FIND_ROOT_PATH_MODE_PACKAGE ONLY)
set(CMAKE_EXE_LINKER_FLAGS "-ldl -lpthread -lm -lutil")
CMAKE;
        // 有时候系统的 cmake 找不到 ar 命令，真奇怪
        if (PHP_OS_FAMILY === 'Linux') {
            $toolchain .= "\nSET(CMAKE_AR \"ar\")";
        }
        FileSystem::writeFile(SOURCE_PATH . '/toolchain.cmake', $toolchain);
        return realpath(SOURCE_PATH . '/toolchain.cmake');
    }

    /**
     * @param  string      $name  命令名称
     * @param  array       $paths 寻找的目标路径（如果不传入，则使用环境变量 PATH）
     * @return null|string 找到了返回命令路径，找不到返回 null
     */
    public static function findCommand(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
            }
        }
        return null;
    }

    /**
     * @param  array  $vars Variables, like: ["CFLAGS" => "-Ixxx"]
     * @return string like: CFLAGS="-Ixxx"
     */
    public static function makeEnvVarString(array $vars): string
    {
        $str = '';
        foreach ($vars as $key => $value) {
            if ($str !== '') {
                $str .= ' ';
            }
            $str .= $key . '=' . escapeshellarg($value);
        }
        return $str;
    }
}
