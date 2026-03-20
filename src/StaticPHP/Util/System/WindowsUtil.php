<?php

declare(strict_types=1);

namespace StaticPHP\Util\System;

use StaticPHP\Util\FileSystem;

class WindowsUtil
{
    private static array|false|null $vsCache = null;

    /**
     * Find Windows program using executable name.
     *
     * @param  string      $name  command name (xxx.exe)
     * @param  array       $paths search path (default use env path)
     * @return null|string null if not found, string is absolute path
     */
    public static function findCommand(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('Path'));
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
     * @return array{
     *     version: string,
     *     major_version: string,
     *     dir: string
     * }|false False if not installed, array contains 'version' and 'dir'
     */
    public static function findVisualStudio(): array|false
    {
        if (self::$vsCache !== null) {
            return self::$vsCache;
        }

        // call vswhere (need VS and C++ tools installed), output is json
        $vswhere_exec = PKG_ROOT_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'vswhere.exe';
        $args = [
            '-latest',
            '-format', 'json',
            '-requires', 'Microsoft.VisualStudio.Component.VC.Tools.x86.x64',
        ];
        $cmd = escapeshellarg($vswhere_exec) . ' ' . implode(' ', $args);
        $result = f_exec($cmd, $out, $code);
        if ($code !== 0 || !$result) {
            return self::$vsCache = false;
        }
        $json = json_decode(implode("\n", $out), true);
        if (!is_array($json) || count($json) === 0) {
            return self::$vsCache = false;
        }
        return self::$vsCache = [
            'version' => $json[0]['installationVersion'],
            'major_version' => explode('.', $json[0]['installationVersion'])[0],
            'dir' => $json[0]['installationPath'],
        ];
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
     * @param null|string $cflags  CFLAGS for cmake, default use '/MT /Os /Ob1 /DNDEBUG /D_ACRTIMP= /D_CRTIMP='
     * @param null|string $ldflags LDFLAGS for cmake, default use '/nodefaultlib:msvcrt /nodefaultlib:msvcrtd /defaultlib:libcmt'
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
        $source = str_replace('\\', '/', SOURCE_PATH);
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
list(PREPEND CMAKE_MODULE_PATH "{$source}/cmake-find")
CMAKE;
        if (!is_dir(SOURCE_PATH)) {
            FileSystem::createDir(SOURCE_PATH);
        }
        FileSystem::writeFile(SOURCE_PATH . '\toolchain.cmake', $toolchain);
        self::writeCmakeFindModules();
        return realpath(SOURCE_PATH . '\toolchain.cmake');
    }

    /**
     * Write cmake-find wrapper modules to source/cmake-find/.
     * These override cmake's built-in Find*.cmake modules to inject
     * Windows-specific static library dependencies (e.g. zlib into OpenSSL).
     * Called both from makeCmakeToolchainFile() and from WindowsCMakeExecutor
     * so the modules are always present even when the static toolchain.cmake
     * file is used directly without regeneration.
     */
    public static function writeCmakeFindModules(): void
    {
        $cmake_find_dir = SOURCE_PATH . DIRECTORY_SEPARATOR . 'cmake-find';
        if (!is_dir($cmake_find_dir)) {
            FileSystem::createDir($cmake_find_dir);
        }
        FileSystem::writeFile($cmake_find_dir . DIRECTORY_SEPARATOR . 'FindOpenSSL.cmake', <<<'CMAKE'
# Custom FindOpenSSL.cmake wrapper for static-php-cli Windows builds.

if(WIN32 AND (OpenSSL_FOUND OR OPENSSL_FOUND))
    list(GET CMAKE_FIND_ROOT_PATH 0 _spc_buildroot)
    # Normalize to forward slashes — backslash paths cause 'Invalid character
    # escape' errors when cmake parses them inside string arguments.
    file(TO_CMAKE_PATH "${_spc_buildroot}" _spc_buildroot)
    set(_spc_zlib "${_spc_buildroot}/lib/zlibstatic.lib")
    if(EXISTS "${_spc_zlib}")
        foreach(_spc_var OPENSSL_LIBRARIES OPENSSL_CRYPTO_LIBRARIES OPENSSL_SSL_LIBRARIES)
            if(DEFINED ${_spc_var} AND NOT "${_spc_zlib}" IN_LIST ${_spc_var})
                list(APPEND ${_spc_var} "${_spc_zlib}")
            endif()
        endforeach()
    endif()
    unset(_spc_buildroot)
    unset(_spc_zlib)
    unset(_spc_var)
endif()
CMAKE);
    }
}
