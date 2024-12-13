<?php

declare(strict_types=1);

namespace SPC\builder\windows;

use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

class SystemUtil
{
    /**
     * Find windows program using executable name.
     *
     * @param  string      $name  command name (xxx.exe)
     * @param  array       $paths search path (default use env path)
     * @return null|string null if not found, string is absolute path
     */
    public static function findCommand(string $name, array $paths = [], bool $include_sdk_bin = false): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('Path'));
            if ($include_sdk_bin) {
                $paths[] = getenv('PHP_SDK_PATH') . '\bin';
            }
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
            }
        }
        return null;
    }

    /**
     * Find Visual Studio installation.
     *
     * @return array<string, string>|false False if not installed, array contains 'version' and 'dir'
     */
    public static function findVisualStudio(): array|false
    {
        $check_path = [
            'C:\Program Files\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\MSBuild.exe' => 'vs17',
            'C:\Program Files\Microsoft Visual Studio\2022\Professional\MSBuild\Current\Bin\MSBuild.exe' => 'vs17',
            'C:\Program Files\Microsoft Visual Studio\2022\Enterprise\MSBuild\Current\Bin\MSBuild.exe' => 'vs17',
            'C:\Program Files (x86)\Microsoft Visual Studio\2019\Community\MSBuild\Current\Bin\MSBuild.exe' => 'vs16',
            'C:\Program Files (x86)\Microsoft Visual Studio\2019\Professional\MSBuild\Current\Bin\MSBuild.exe' => 'vs16',
            'C:\Program Files (x86)\Microsoft Visual Studio\2019\Enterprise\MSBuild\Current\Bin\MSBuild.exe' => 'vs16',
        ];
        foreach ($check_path as $path => $vs_version) {
            if (file_exists($path)) {
                $vs_ver = $vs_version;
                $d_dir = dirname($path, 4);
                return [
                    'version' => $vs_ver,
                    'dir' => $d_dir,
                ];
            }
        }
        return false;
    }

    /**
     * Get CPU count for concurrency.
     */
    public static function getCpuCount(): int
    {
        $result = f_exec('echo %NUMBER_OF_PROCESSORS%', $out, $code);
        if ($code !== 0 || !$result) {
            return 1;
        }
        return intval($result);
    }

    /**
     * Create CMake toolchain file.
     *
     * @param  null|string         $cflags  CFLAGS for cmake, default use '/MT /Os /Ob1 /DNDEBUG /D_ACRTIMP= /D_CRTIMP='
     * @param  null|string         $ldflags LDFLAGS for cmake, default use '/nodefaultlib:msvcrt /nodefaultlib:msvcrtd /defaultlib:libcmt'
     * @throws FileSystemException
     */
    public static function makeCmakeToolchainFile(?string $cflags = null, ?string $ldflags = null): string
    {
        if ($cflags === null) {
            $cflags = '/MT /Os /Ob1 /DNDEBUG /D_ACRTIMP= /D_CRTIMP=';
        }
        if ($ldflags === null) {
            $ldflags = '/nodefaultlib:msvcrt /nodefaultlib:msvcrtd /defaultlib:libcmt';
        }
        $buildroot = str_replace('\\', '\\\\', BUILD_ROOT_PATH);
        $toolchain = <<<CMAKE
set(CMAKE_SYSTEM_NAME Windows)
SET(CMAKE_SYSTEM_PROCESSOR x64)
SET(CMAKE_C_FLAGS "{$cflags}")
SET(CMAKE_C_FLAGS_DEBUG "{$cflags}")
SET(CMAKE_CXX_FLAGS "{$cflags}")
SET(CMAKE_CXX_FLAGS_DEBUG "{$cflags}")
SET(CMAKE_EXE_LINKER_FLAGS "{$ldflags}")
SET(CMAKE_FIND_ROOT_PATH "{$buildroot}")
SET(CMAKE_MSVC_RUNTIME_LIBRARY MultiThreaded)
CMAKE;
        FileSystem::writeFile(SOURCE_PATH . '\toolchain.cmake', $toolchain);
        return realpath(SOURCE_PATH . '\toolchain.cmake');
    }
}
