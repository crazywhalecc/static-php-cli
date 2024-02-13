<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\BuilderBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class SourcePatcher
{
    public static function init(): void
    {
        // FileSystem::addSourceExtractHook('swow', [SourcePatcher::class, 'patchSwow']);
        FileSystem::addSourceExtractHook('micro', [SourcePatcher::class, 'patchMicro']);
        FileSystem::addSourceExtractHook('openssl', [SourcePatcher::class, 'patchOpenssl11Darwin']);
        FileSystem::addSourceExtractHook('swoole', [SourcePatcher::class, 'patchSwoole']);
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
        // patch windows php 8.1 bug
        if (PHP_OS_FAMILY === 'Windows' && $builder->getPHPVersionID() >= 80100 && $builder->getPHPVersionID() < 80200) {
            logger()->info('Patching PHP 8.1 windows Fiber bug');
            FileSystem::replaceFileStr(
                SOURCE_PATH . '\php-src\win32\build\config.w32',
                "ADD_FLAG('LDFLAGS', '$(BUILD_DIR)\\\\Zend\\\\jump_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');",
                "ADD_FLAG('ASM_OBJS', '$(BUILD_DIR)\\\\Zend\\\\jump_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj $(BUILD_DIR)\\\\Zend\\\\make_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');"
            );
            FileSystem::replaceFileStr(
                SOURCE_PATH . '\php-src\win32\build\config.w32',
                "ADD_FLAG('LDFLAGS', '$(BUILD_DIR)\\\\Zend\\\\make_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');",
                ''
            );
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
        $serial = ['80', '81', '82', '83', '84'];
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
     * Use existing patch file for patching
     *
     * @param  string           $patch_name Patch file name in src/globals/patch/
     * @param  string           $cwd        Working directory for patch command
     * @param  bool             $reverse    Reverse patches (default: False)
     * @throws RuntimeException
     */
    public static function patchFile(string $patch_name, string $cwd, bool $reverse = false): bool
    {
        if (!file_exists(ROOT_DIR . "/src/globals/patch/{$patch_name}")) {
            return false;
        }

        $patch_file = ROOT_DIR . "/src/globals/patch/{$patch_name}";
        $patch_str = str_replace('/', DIRECTORY_SEPARATOR, $patch_file);

        // copy patch from phar
        if (\Phar::running() !== '') {
            file_put_contents(SOURCE_PATH . '/' . $patch_name, file_get_contents($patch_file));
            $patch_str = str_replace('/', DIRECTORY_SEPARATOR, SOURCE_PATH . '/' . $patch_name);
        }

        f_passthru(
            'cd ' . $cwd . ' && ' .
            (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . $patch_str . ' | patch -p1 ' . ($reverse ? '-R' : '')
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

    public static function patchSwoole(): bool
    {
        // swoole hook needs pdo/pdo.h
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/swoole/config.m4',
            'PHP_ADD_INCLUDE([$ext_srcdir])',
            "PHP_ADD_INCLUDE( [\$ext_srcdir] )\n    PHP_ADD_INCLUDE([\$abs_srcdir/ext])"
        );
        return true;
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
        if ($builder instanceof UnixBuilderBase) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/Makefile', 'install-micro', '');
        }

        // Prevent event extension compile error on macOS
        if ($builder instanceof MacOSBuilder) {
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/main/php_config.h', '/^#define HAVE_OPENPTY 1$/m', '');
        }

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

    /**
     * Patch cli SAPI Makefile for Windows.
     *
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function patchWindowsCLITarget(): void
    {
        // search Makefile code line contains "$(BUILD_DIR)\php.exe:"
        $content = FileSystem::readFile(SOURCE_PATH . '/php-src/Makefile');
        $lines = explode("\r\n", $content);
        $line_num = 0;
        $found = false;
        foreach ($lines as $v) {
            if (strpos($v, '$(BUILD_DIR)\php.exe:') !== false) {
                $found = $line_num;
                break;
            }
            ++$line_num;
        }
        if ($found === false) {
            throw new RuntimeException('Cannot patch windows CLI Makefile!');
        }
        $lines[$line_num] = '$(BUILD_DIR)\php.exe: generated_files $(DEPS_CLI) $(PHP_GLOBAL_OBJS) $(CLI_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php.exe.res $(BUILD_DIR)\php.exe.manifest';
        $lines[$line_num + 1] = "\t" . '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(ASM_OBJS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286';
        FileSystem::writeFile(SOURCE_PATH . '/php-src/Makefile', implode("\r\n", $lines));
    }

    /**
     * Add additional `static-php-cli.version` ini value for PHP source.
     *
     * @throws FileSystemException
     */
    public static function patchSPCVersionToPHP(string $version = 'unknown'): void
    {
        // detect patch
        $file = FileSystem::readFile(SOURCE_PATH . '/php-src/main/main.c');
        if (!str_contains($file, 'static-php-cli.version')) {
            logger()->debug('Inserting static-php-cli.version to php-src');
            $file = str_replace('PHP_INI_BEGIN()', "PHP_INI_BEGIN()\n\tPHP_INI_ENTRY(\"static-php-cli.version\",\t\"{$version}\",\tPHP_INI_ALL,\tNULL)", $file);
            FileSystem::writeFile(SOURCE_PATH . '/php-src/main/main.c', $file);
        }
    }
}
