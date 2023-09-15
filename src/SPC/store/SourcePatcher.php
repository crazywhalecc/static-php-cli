<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\BuilderBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class SourcePatcher
{
    public static function init(): void
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
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/have_capstone="yes"/', 'have_capstone="no"');
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
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
        // $check = !defined('DEBUG_MODE') ? ' -q' : '';
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
        $serial = ['80', '81', '82', '83'];
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

    /**
     * @throws FileSystemException
     */
    public static function patchOpenssl11Darwin(): bool
    {
        if (PHP_OS_FAMILY === 'Darwin' && !file_exists(SOURCE_PATH . '/openssl/VERSION.dat') && file_exists(SOURCE_PATH . '/openssl/test/v3ext.c')) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/openssl/test/v3ext.c', '#include <stdio.h>', '#include <stdio.h>' . PHP_EOL . '#include <string.h>');
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
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_STRLCPY 1$/m', '');
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_STRLCAT 1$/m', '');
        }
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_OPENPTY 1$/m', '');

        // call extension patch before make
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeMake() === true) {
                logger()->info('Extension [' . $ext->getName() . '] patched before make');
            }
        }
    }

    /**
     * @throws FileSystemException
     */
    public static function patchHardcodedINI(array $ini = []): bool
    {
        $cli_c = SOURCE_PATH . '/php-src/sapi/cli/php_cli.c';
        $cli_c_bak = SOURCE_PATH . '/php-src/sapi/cli/php_cli.c.bak';
        $micro_c = SOURCE_PATH . '/php-src/sapi/micro/php_micro.c';
        $micro_c_bak = SOURCE_PATH . '/php-src/sapi/micro/php_micro.c.bak';
        $embed_c = SOURCE_PATH . '/php-src/sapi/embed/php_embed.c';
        $embed_c_bak = SOURCE_PATH . '/php-src/sapi/embed/php_embed.c.bak';

        // Try to reverse backup file
        $find_str = 'const char HARDCODED_INI[] =';
        $patch_str = '';
        foreach ($ini as $key => $value) {
            $patch_str .= "\"{$key}={$value}\\n\"\n";
        }
        $patch_str = "const char HARDCODED_INI[] =\n{$patch_str}";

        // Detect backup, if we have backup, it means we need to reverse first
        if (file_exists($cli_c_bak) || file_exists($micro_c_bak) || file_exists($embed_c_bak)) {
            self::unpatchHardcodedINI();
        }

        // Backup it
        $result = file_put_contents($cli_c_bak, file_get_contents($cli_c));
        $result = $result && file_put_contents($micro_c_bak, file_get_contents($micro_c));
        $result = $result && file_put_contents($embed_c_bak, file_get_contents($embed_c));
        if ($result === false) {
            return false;
        }

        // Patch it
        FileSystem::replaceFileStr($cli_c, $find_str, $patch_str);
        FileSystem::replaceFileStr($micro_c, $find_str, $patch_str);
        FileSystem::replaceFileStr($embed_c, $find_str, $patch_str);
        return true;
    }

    public static function unpatchHardcodedINI(): bool
    {
        $cli_c = SOURCE_PATH . '/php-src/sapi/cli/php_cli.c';
        $cli_c_bak = SOURCE_PATH . '/php-src/sapi/cli/php_cli.c.bak';
        $micro_c = SOURCE_PATH . '/php-src/sapi/micro/php_micro.c';
        $micro_c_bak = SOURCE_PATH . '/php-src/sapi/micro/php_micro.c.bak';
        $embed_c = SOURCE_PATH . '/php-src/sapi/embed/php_embed.c';
        $embed_c_bak = SOURCE_PATH . '/php-src/sapi/embed/php_embed.c.bak';
        if (!file_exists($cli_c_bak) && !file_exists($micro_c_bak) && !file_exists($embed_c_bak)) {
            return false;
        }
        $result = file_put_contents($cli_c, file_get_contents($cli_c_bak));
        $result = $result && file_put_contents($micro_c, file_get_contents($micro_c_bak));
        $result = $result && file_put_contents($embed_c, file_get_contents($embed_c_bak));
        @unlink($cli_c_bak);
        @unlink($micro_c_bak);
        @unlink($embed_c_bak);
        return $result;
    }
}
