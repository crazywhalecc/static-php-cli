<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\BuilderBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class SourcePatcher
{
    public static function init()
    {
        // FileSystem::addSourceExtractHook('swow', [SourcePatcher::class, 'patchSwow']);
        FileSystem::addSourceExtractHook('micro', [SourcePatcher::class, 'patchMicro']);
        FileSystem::addSourceExtractHook('openssl', [SourcePatcher::class, 'patchOpenssl11Darwin']);
    }

    /**
     * Source patcher runner before buildconf
     *
     * @param BuilderBase $builder Builder
     */
    public static function patchBeforeBuildconf(BuilderBase $builder): void
    {
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeBuildconf() === true) {
                logger()->info('Extension [' . $ext->getName() . '] patched before buildconf');
            }
        }
    }

    /**
     * Source patcher runner before configure
     *
     * @param  BuilderBase         $builder Builder
     * @throws FileSystemException
     */
    public static function patchBeforeConfigure(BuilderBase $builder): void
    {
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeConfigure() === true) {
                logger()->info('Extension [' . $ext->getName() . '] patched before configure');
            }
        }
        // patch capstone
        FileSystem::replaceFile(SOURCE_PATH . '/php-src/configure', REPLACE_FILE_PREG, '/have_capstone="yes"/', 'have_capstone="no"');
    }

    public static function patchMicro(?array $list = null, bool $reverse = false): bool
    {
        if (!file_exists(SOURCE_PATH . '/php-src/sapi/micro/php_micro.c')) {
            return false;
        }
        $ver_file = SOURCE_PATH . '/php-src/main/php_version.h';
        if (!file_exists($ver_file)) {
            throw new FileSystemException('Patch failed, cannot find php source files');
        }
        $version_h = FileSystem::readFile(SOURCE_PATH . '/php-src/main/php_version.h');
        preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)\s+#\s*define\s+PHP_MINOR_VERSION\s+(\d+)\s+/m', $version_h, $match);
        // $ver = "{$match[1]}.{$match[2]}";

        $major_ver = $match[1] . $match[2];
        if ($major_ver === '74') {
            return false;
        }
        $check = !defined('DEBUG_MODE') ? ' -q' : '';
        // f_passthru('cd ' . SOURCE_PATH . '/php-src && git checkout' . $check . ' HEAD');

        $default = [
            'static_opcache',
            'static_extensions_win32',
            'cli_checks',
            'disable_huge_page',
            'vcruntime140',
            'win32',
            'zend_stream',
        ];
        if (PHP_OS_FAMILY === 'Windows') {
            $default[] = 'cli_static';
        }
        if (PHP_OS_FAMILY === 'Darwin') {
            $default[] = 'macos_iconv';
        }
        $patch_list = $list ?? $default;
        $patches = [];
        $serial = ['80', '81', '82'];
        foreach ($patch_list as $patchName) {
            if (file_exists(SOURCE_PATH . "/php-src/sapi/micro/patches/{$patchName}.patch")) {
                $patches[] = "sapi/micro/patches/{$patchName}.patch";
                continue;
            }
            for ($i = array_search($major_ver, $serial, true); $i >= 0; --$i) {
                $tryMajMin = $serial[$i];
                if (!file_exists(SOURCE_PATH . "/php-src/sapi/micro/patches/{$patchName}_{$tryMajMin}.patch")) {
                    continue;
                }
                $patches[] = "sapi/micro/patches/{$patchName}_{$tryMajMin}.patch";
                continue 2;
            }
            throw new RuntimeException("failed finding patch {$patchName}");
        }

        $patchesStr = str_replace('/', DIRECTORY_SEPARATOR, implode(' ', $patches));

        f_passthru(
            'cd ' . SOURCE_PATH . '/php-src && ' .
            (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . $patchesStr . ' | patch -p1 ' . ($reverse ? '-R' : '')
        );

        return true;
    }

    public static function patchOpenssl11Darwin(): bool
    {
        if (PHP_OS_FAMILY === 'Darwin' && !file_exists(SOURCE_PATH . '/openssl/VERSION.dat') && file_exists(SOURCE_PATH . '/openssl/test/v3ext.c')) {
            FileSystem::replaceFile(
                SOURCE_PATH . '/openssl/test/v3ext.c',
                REPLACE_FILE_STR,
                '#include <stdio.h>',
                '#include <stdio.h>' . PHP_EOL . '#include <string.h>'
            );
            return true;
        }
        return false;
    }

    /**
     * @throws FileSystemException
     */
    public static function patchBeforeMake(BuilderBase $builder): void
    {
        // Try to fix debian environment build lack HAVE_STRLCAT problem
        if ($builder instanceof LinuxBuilder) {
            FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCPY 1$/m', '');
            FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCAT 1$/m', '');
        }
        FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_OPENPTY 1$/m', '');

        // call extension patch before make
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeMake() === true) {
                logger()->info('Extension [' . $ext->getName() . '] patched before make');
            }
        }
    }
}
