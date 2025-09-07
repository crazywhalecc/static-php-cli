<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\BuilderBase;
use SPC\builder\linux\SystemUtil;
use SPC\builder\unix\UnixBuilderBase;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\ExecutionException;
use SPC\exception\FileSystemException;
use SPC\exception\PatchException;
use SPC\util\SPCTarget;

class SourcePatcher
{
    public static function init(): void
    {
        // FileSystem::addSourceExtractHook('swow', [__CLASS__, 'patchSwow']);
        FileSystem::addSourceExtractHook('openssl', [__CLASS__, 'patchOpenssl11Darwin']);
        FileSystem::addSourceExtractHook('swoole', [__CLASS__, 'patchSwoole']);
        FileSystem::addSourceExtractHook('php-src', [__CLASS__, 'patchPhpLibxml212']);
        FileSystem::addSourceExtractHook('php-src', [__CLASS__, 'patchGDWin32']);
        FileSystem::addSourceExtractHook('php-src', [__CLASS__, 'patchFfiCentos7FixO3strncmp']);
        FileSystem::addSourceExtractHook('sqlsrv', [__CLASS__, 'patchSQLSRVWin32']);
        FileSystem::addSourceExtractHook('pdo_sqlsrv', [__CLASS__, 'patchSQLSRVWin32']);
        FileSystem::addSourceExtractHook('yaml', [__CLASS__, 'patchYamlWin32']);
        FileSystem::addSourceExtractHook('libyaml', [__CLASS__, 'patchLibYaml']);
        FileSystem::addSourceExtractHook('php-src', [__CLASS__, 'patchImapLicense']);
        FileSystem::addSourceExtractHook('ext-imagick', [__CLASS__, 'patchImagickWith84']);
        FileSystem::addSourceExtractHook('libaom', [__CLASS__, 'patchLibaomForAlpine']);
        FileSystem::addSourceExtractHook('pkg-config', [__CLASS__, 'patchPkgConfigForGcc15']);
        FileSystem::addSourceExtractHook('attr', [__CLASS__, 'patchAttrForAlpine']);
        FileSystem::addSourceExtractHook('gmssl', [__CLASS__, 'patchGMSSL']);
    }

    public static function patchBeforeBuildconf(BuilderBase $builder): void
    {
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeBuildconf() === true) {
                logger()->info("Extension [{$ext->getName()}] patched before buildconf");
            }
        }
        foreach ($builder->getLibs() as $lib) {
            if ($lib->patchBeforeBuildconf() === true) {
                logger()->info("Library [{$lib->getName()}]patched before buildconf");
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

        // Fix PHP VS version
        if ($builder instanceof WindowsBuilder) {
            // get vs version
            $vc = \SPC\builder\windows\SystemUtil::findVisualStudio();
            $vc_matches = match ($vc['version']) {
                'vs17' => ['VS17', 'Visual C++ 2022'],
                'vs16' => ['VS16', 'Visual C++ 2019'],
                default => ['unknown', 'unknown'],
            };
            // patch php-src/win32/build/confutils.js
            FileSystem::replaceFileStr(
                SOURCE_PATH . '\php-src\win32\build\confutils.js',
                'var name = "unknown";',
                "var name = short ? \"{$vc_matches[0]}\" : \"{$vc_matches[1]}\";return name;"
            );
        }

        // patch configure.ac
        $musl = SPCTarget::getLibc() === 'musl';
        FileSystem::backupFile(SOURCE_PATH . '/php-src/configure.ac');
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/configure.ac',
            'if command -v ldd >/dev/null && ldd --version 2>&1 | grep ^musl >/dev/null 2>&1',
            'if ' . ($musl ? 'true' : 'false')
        );
        if (getenv('SPC_LIBC') === false && ($libc = SPCTarget::getLibc()) !== null) {
            putenv("SPC_LIBC={$libc}");
        }

        // patch php-src/build/php.m4 PKG_CHECK_MODULES -> PKG_CHECK_MODULES_STATIC
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/build/php.m4', 'PKG_CHECK_MODULES(', 'PKG_CHECK_MODULES_STATIC(');

        if ($builder->getOption('enable-micro-win32')) {
            self::patchMicroWin32();
        } else {
            self::unpatchMicroWin32();
        }
    }

    /**
     * Source patcher runner before configure
     *
     * @param BuilderBase $builder Builder
     */
    public static function patchBeforeConfigure(BuilderBase $builder): void
    {
        foreach ($builder->getExts() as $ext) {
            $patch = $builder instanceof WindowsBuilder ? $ext->patchBeforeWindowsConfigure() : $ext->patchBeforeConfigure();
            if ($patch === true) {
                logger()->info("Extension [{$ext->getName()}] patched before configure");
            }
        }
        foreach ($builder->getLibs() as $lib) {
            if ($lib->patchBeforeConfigure() === true) {
                logger()->info("Library [{$lib->getName()}] patched before configure");
            }
        }
        // patch capstone
        if (is_unix()) {
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/have_capstone="yes"/', 'have_capstone="no"');
        }

        if (file_exists(SOURCE_PATH . '/php-src/configure.ac.bak')) {
            // restore configure.ac
            FileSystem::restoreBackupFile(SOURCE_PATH . '/php-src/configure.ac');
        }
    }

    public static function patchMicro(?array $items = null): bool
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

        if ($items !== null) {
            $spc_micro_patches = $items;
        } else {
            $spc_micro_patches = getenv('SPC_MICRO_PATCHES');
            $spc_micro_patches = $spc_micro_patches === false ? [] : explode(',', $spc_micro_patches);
        }
        $spc_micro_patches = array_filter($spc_micro_patches, fn ($item) => trim((string) $item) !== '');
        $patch_list = $spc_micro_patches;
        $patches = [];
        $serial = ['80', '81', '82', '83', '84', '85'];
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
            throw new PatchException('phpmicro patches', "Failed finding patch file or versioned file {$patchName} !");
        }

        foreach ($patches as $patch) {
            logger()->info("Patching micro with {$patch}");
            self::patchFile(SOURCE_PATH . "/php-src/{$patch}", SOURCE_PATH . '/php-src');
        }

        return true;
    }

    /**
     * Use existing patch file for patching
     *
     * @param string $patch_name Patch file name in src/globals/patch/ or absolute path
     * @param string $cwd        Working directory for patch command
     * @param bool   $reverse    Reverse patches (default: False)
     */
    public static function patchFile(string $patch_name, string $cwd, bool $reverse = false): bool
    {
        try {
            if (FileSystem::isRelativePath($patch_name)) {
                $patch_file = ROOT_DIR . "/src/globals/patch/{$patch_name}";
            } else {
                $patch_file = $patch_name;
            }
            if (!file_exists($patch_file)) {
                return false;
            }

            $patch_str = FileSystem::convertPath($patch_file);
            if (!file_exists($patch_str)) {
                throw new PatchException($patch_name, "Patch file [{$patch_str}] does not exist");
            }

            // Copy patch from phar
            if (str_starts_with($patch_str, 'phar://')) {
                $filename = pathinfo($patch_file, PATHINFO_BASENAME);
                file_put_contents(SOURCE_PATH . "/{$filename}", file_get_contents($patch_file));
                $patch_str = FileSystem::convertPath(SOURCE_PATH . "/{$filename}");
            }

            // detect
            $detect_reverse = !$reverse;
            $detect_cmd = 'cd ' . escapeshellarg($cwd) . ' && '
                . (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . escapeshellarg($patch_str)
                . ' | patch --dry-run -p1 -s -f ' . ($detect_reverse ? '-R' : '')
                . ' > ' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null') . ' 2>&1';
            exec($detect_cmd, $output, $detect_status);

            if ($detect_status === 0) {
                return true;
            }

            // apply patch
            $apply_cmd = 'cd ' . escapeshellarg($cwd) . ' && '
                . (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . escapeshellarg($patch_str)
                . ' | patch -p1 ' . ($reverse ? '-R' : '');

            f_passthru($apply_cmd);
            return true;
        } catch (ExecutionException $e) {
            // If patch failed, throw exception
            throw new PatchException($patch_name, "Patch file [{$patch_name}] failed to apply", previous: $e);
        }
    }

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

        // swoole 5.1.3 build fix
        // get swoole version first
        $file = SOURCE_PATH . '/php-src/ext/swoole/include/swoole_version.h';
        // Match #define SWOOLE_VERSION "5.1.3"
        $pattern = '/#define SWOOLE_VERSION "(.+)"/';
        if (preg_match($pattern, file_get_contents($file), $matches)) {
            $version = $matches[1];
        } else {
            $version = '1.0.0';
        }
        if ($version === '5.1.3') {
            self::patchFile('spc_fix_swoole_50513.patch', SOURCE_PATH . '/php-src/ext/swoole');
        }
        if (version_compare($version, '6.0.0', '>=') && version_compare($version, '6.1.0', '<')) {
            // remove when https://github.com/swoole/swoole-src/pull/5848 is merged
            self::patchFile('swoole_fix_date_time.patch', SOURCE_PATH . '/php-src/ext/swoole');
            // remove when https://github.com/swoole/swoole-src/pull/5847 is merged
            self::patchFile('swoole_fix_odbclibs.patch', SOURCE_PATH . '/php-src/ext/swoole');
        }
        return true;
    }

    public static function patchBeforeMake(BuilderBase $builder): void
    {
        if ($builder instanceof UnixBuilderBase) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/Makefile', 'install-micro', '');
        }
        if (!SPCTarget::isStatic() && SPCTarget::getLibc() === 'musl') {
            // we need to patch the symbol to global visibility, otherwise extensions with `initial-exec` TLS model will fail to load
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/TSRM/TSRM.h',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS __attribute__((visibility("default"))) void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
            );
        } else {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/TSRM/TSRM.h',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS __attribute__((visibility("default"))) void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
                '#define TSRMLS_MAIN_CACHE_DEFINE() TSRM_TLS void *TSRMLS_CACHE TSRM_TLS_MODEL_ATTR = NULL;',
            );
        }

        // no asan
        // if (strpos(file_get_contents(SOURCE_PATH . '/php-src/Makefile'), 'CFLAGS_CLEAN = -g') === false) {
        //     FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/Makefile', 'CFLAGS_CLEAN = ', 'CFLAGS_CLEAN = -g -fsanitize=address ');
        // }

        // call extension patch before make
        foreach ($builder->getExts() as $ext) {
            if ($ext->patchBeforeMake() === true) {
                logger()->info("Extension [{$ext->getName()}] patched before make");
            }
        }
        foreach ($builder->getLibs() as $lib) {
            if ($lib->patchBeforeMake() === true) {
                logger()->info("Library [{$lib->getName()}] patched before make");
            }
        }

        if (str_contains((string) getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS'), '-release')) {
            FileSystem::replaceFileLineContainsString(
                SOURCE_PATH . '/php-src/ext/standard/info.c',
                '#ifdef CONFIGURE_COMMAND',
                '#ifdef NO_CONFIGURE_COMMAND',
            );
        } else {
            FileSystem::replaceFileLineContainsString(
                SOURCE_PATH . '/php-src/ext/standard/info.c',
                '#ifdef NO_CONFIGURE_COMMAND',
                '#ifdef CONFIGURE_COMMAND',
            );
        }
    }

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

    public static function patchMicroPhar(int $version_id): void
    {
        FileSystem::backupFile(SOURCE_PATH . '/php-src/ext/phar/phar.c');
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/phar/phar.c',
            'static zend_op_array *phar_compile_file',
            "char *micro_get_filename(void);\n\nstatic zend_op_array *phar_compile_file"
        );
        if ($version_id < 80100) {
            // PHP 8.0.x
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/phar/phar.c',
                'if (strstr(file_handle->filename, ".phar") && !strstr(file_handle->filename, "://")) {',
                'if ((strstr(file_handle->filename, micro_get_filename()) || strstr(file_handle->filename, ".phar")) && !strstr(file_handle->filename, "://")) {'
            );
        } else {
            // PHP >= 8.1
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/phar/phar.c',
                'if (strstr(ZSTR_VAL(file_handle->filename), ".phar") && !strstr(ZSTR_VAL(file_handle->filename), "://")) {',
                'if ((strstr(ZSTR_VAL(file_handle->filename), micro_get_filename()) || strstr(ZSTR_VAL(file_handle->filename), ".phar")) && !strstr(ZSTR_VAL(file_handle->filename), "://")) {'
            );
        }
    }

    public static function unpatchMicroPhar(): void
    {
        FileSystem::restoreBackupFile(SOURCE_PATH . '/php-src/ext/phar/phar.c');
    }

    /**
     * Fix the compilation issue of sqlsrv and pdo_sqlsrv on Windows (/sdl check is too strict and will cause Zend compilation to fail)
     */
    public static function patchSQLSRVWin32(string $source_name): bool
    {
        $source_name = preg_replace('/[^a-z_]/', '', $source_name);
        if (file_exists(SOURCE_PATH . '/php-src/ext/' . $source_name . '/config.w32')) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/' . $source_name . '/config.w32', '/sdl', '');
            return true;
        }
        return false;
    }

    public static function patchYamlWin32(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/yaml/config.w32', "lib.substr(lib.length - 6, 6) == '_a.lib'", "lib.substr(lib.length - 6, 6) == '_a.lib' || 'yes' == 'yes'");
        return true;
    }

    public static function patchLibYaml(string $name, string $target): bool
    {
        if (!file_exists("{$target}/cmake/config.h.in")) {
            FileSystem::createDir("{$target}/cmake");
            copy(ROOT_DIR . '/src/globals/extra/libyaml_config.h.in', "{$target}/cmake/config.h.in");
        }
        if (!file_exists("{$target}/YamlConfig.cmake.in")) {
            copy(ROOT_DIR . '/src/globals/extra/libyaml_yamlConfig.cmake.in', "{$target}/yamlConfig.cmake.in");
        }
        return true;
    }

    /**
     * Patch imap license file for PHP < 8.4
     */
    public static function patchImapLicense(): bool
    {
        if (!file_exists(SOURCE_PATH . '/php-src/ext/imap/LICENSE') && is_dir(SOURCE_PATH . '/php-src/ext/imap')) {
            file_put_contents(SOURCE_PATH . '/php-src/ext/imap/LICENSE', file_get_contents(ROOT_DIR . '/src/globals/extra/Apache_LICENSE'));
            return true;
        }
        return false;
    }

    /**
     * Patch imagick for PHP 8.4
     */
    public static function patchImagickWith84(): bool
    {
        // match imagick version id
        $file = SOURCE_PATH . '/php-src/ext/imagick/php_imagick.h';
        if (!file_exists($file)) {
            return false;
        }
        $content = file_get_contents($file);
        if (preg_match('/#define PHP_IMAGICK_EXTNUM\s+(\d+)/', $content, $match) === 0) {
            return false;
        }
        $extnum = intval($match[1]);
        if ($extnum < 30800) {
            self::patchFile('imagick_php84_before_30800.patch', SOURCE_PATH . '/php-src/ext/imagick');
            return true;
        }
        return false;
    }

    public static function patchFfiCentos7FixO3strncmp(): bool
    {
        if (!($ver = SPCTarget::getLibcVersion()) || version_compare($ver, '2.17', '>')) {
            return false;
        }
        if (!file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            return false;
        }
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0 && intval($match[1]) < 80316) {
            return false;
        }
        self::patchFile('ffi_centos7_fix_O3_strncmp.patch', SOURCE_PATH . '/php-src');
        return true;
    }

    public static function patchPkgConfigForGcc15(): bool
    {
        self::patchFile('pkg-config_gcc15.patch', SOURCE_PATH . '/pkg-config');
        return true;
    }

    public static function patchLibaomForAlpine(): bool
    {
        if (PHP_OS_FAMILY === 'Linux' && SystemUtil::isMuslDist()) {
            self::patchFile('libaom_posix_implict.patch', SOURCE_PATH . '/libaom');
            return true;
        }
        return false;
    }

    public static function patchAttrForAlpine(): bool
    {
        if (PHP_OS_FAMILY === 'Linux' && SystemUtil::isMuslDist() || PHP_OS_FAMILY === 'Darwin') {
            self::patchFile('attr_alpine_gethostname.patch', SOURCE_PATH . '/attr');
            return true;
        }
        return false;
    }

    /**
     * Patch cli SAPI Makefile for Windows.
     */
    public static function patchWindowsCLITarget(): void
    {
        // search Makefile code line contains "$(BUILD_DIR)\php.exe:"
        $content = FileSystem::readFile(SOURCE_PATH . '/php-src/Makefile');
        $lines = explode("\r\n", $content);
        $line_num = 0;
        $found = false;
        foreach ($lines as $v) {
            if (str_contains($v, '$(BUILD_DIR)\php.exe:')) {
                $found = $line_num;
                break;
            }
            ++$line_num;
        }
        if ($found === false) {
            throw new PatchException('Windows Makefile patching for php.exe target', 'Cannot patch windows CLI Makefile, Makefile does not contain "$(BUILD_DIR)\php.exe:" line');
        }
        $lines[$line_num] = '$(BUILD_DIR)\php.exe: generated_files $(DEPS_CLI) $(PHP_GLOBAL_OBJS) $(CLI_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php.exe.res $(BUILD_DIR)\php.exe.manifest';
        $lines[$line_num + 1] = "\t" . '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(ASM_OBJS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286';
        FileSystem::writeFile(SOURCE_PATH . '/php-src/Makefile', implode("\r\n", $lines));
    }

    public static function patchPhpLibxml212(): bool
    {
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0) {
            $ver_id = intval($match[1]);
            if ($ver_id < 80000) {
                self::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
                return true;
            }
            if ($ver_id < 80100) {
                self::patchFile('spc_fix_libxml2_12_php80.patch', SOURCE_PATH . '/php-src');
                self::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
                return true;
            }
            if ($ver_id < 80200) {
                // self::patchFile('spc_fix_libxml2_12_php81.patch', SOURCE_PATH . '/php-src');
                self::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
                return true;
            }
            return false;
        }
        return false;
    }

    public static function patchGDWin32(): bool
    {
        $file = file_get_contents(SOURCE_PATH . '/php-src/main/php_version.h');
        if (preg_match('/PHP_VERSION_ID (\d+)/', $file, $match) !== 0) {
            $ver_id = intval($match[1]);
            if ($ver_id < 80200) {
                // see: https://github.com/php/php-src/commit/243966177e39eb71822935042c3f13fa6c5b9eed
                FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/gd/libgd/gdft.c', '#ifndef MSWIN32', '#ifndef _WIN32');
            }
            // custom config.w32, because official config.w32 is hard-coded many things
            $origin = $ver_id >= 80100 ? file_get_contents(ROOT_DIR . '/src/globals/extra/gd_config_81.w32') : file_get_contents(ROOT_DIR . '/src/globals/extra/gd_config_80.w32');
            file_put_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32.bak', file_get_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32'));
            return file_put_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32', $origin) !== false;
        }
        return false;
    }

    /**
     * Add additional `static-php-cli.version` ini value for PHP source.
     */
    public static function patchSPCVersionToPHP(string $version = 'unknown'): void
    {
        // detect patch (remove this when 8.3 deprecated)
        $file = FileSystem::readFile(SOURCE_PATH . '/php-src/main/main.c');
        if (!str_contains($file, 'static-php-cli.version')) {
            logger()->debug('Inserting static-php-cli.version to php-src');
            $file = str_replace('PHP_INI_BEGIN()', "PHP_INI_BEGIN()\n\tPHP_INI_ENTRY(\"static-php-cli.version\",\t\"{$version}\",\tPHP_INI_ALL,\tNULL)", $file);
            FileSystem::writeFile(SOURCE_PATH . '/php-src/main/main.c', $file);
        }
    }

    public static function patchMicroWin32(): void
    {
        // patch micro win32
        if (!file_exists(SOURCE_PATH . '\php-src\sapi\micro\php_micro.c.win32bak')) {
            copy(SOURCE_PATH . '\php-src\sapi\micro\php_micro.c', SOURCE_PATH . '\php-src\sapi\micro\php_micro.c.win32bak');
            FileSystem::replaceFileStr(SOURCE_PATH . '\php-src\sapi\micro\php_micro.c', '#include "php_variables.h"', '#include "php_variables.h"' . "\n#define PHP_MICRO_WIN32_NO_CONSOLE 1");
        }
    }

    public static function unpatchMicroWin32(): void
    {
        if (file_exists(SOURCE_PATH . '\php-src\sapi\micro\php_micro.c.win32bak')) {
            rename(SOURCE_PATH . '\php-src\sapi\micro\php_micro.c.win32bak', SOURCE_PATH . '\php-src\sapi\micro\php_micro.c');
        }
    }

    public static function patchGMSSL(): void
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/gmssl/src/hex.c', 'unsigned char *OPENSSL_hexstr2buf(const char *str, size_t *len)', 'unsigned char *GMSSL_hexstr2buf(const char *str, size_t *len)');
        FileSystem::replaceFileStr(SOURCE_PATH . '/gmssl/src/hex.c', 'OPENSSL_hexchar2int', 'GMSSL_hexchar2int');
    }
}
