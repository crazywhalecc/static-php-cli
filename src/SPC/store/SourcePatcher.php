<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\builder\BuilderBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\Util;

class SourcePatcher
{
    public static function init()
    {
        FileSystem::addSourceExtractHook('swow', [SourcePatcher::class, 'patchSwow']);
        FileSystem::addSourceExtractHook('micro', [SourcePatcher::class, 'patchMicro']);
        FileSystem::addSourceExtractHook('openssl', [SourcePatcher::class, 'patchOpenssl11Darwin']);
    }

    public static function patchPHPBuildconf(BuilderBase $builder): void
    {
        if ($builder->getExt('curl')) {
            logger()->info('patching before-configure for curl checks');
            $file1 = "AC_DEFUN([PHP_CHECK_LIBRARY], [\n  $3\n])";
            $files = FileSystem::readFile(SOURCE_PATH . '/php-src/ext/curl/config.m4');
            $file2 = 'AC_DEFUN([PHP_CHECK_LIBRARY], [
  save_old_LDFLAGS=$LDFLAGS
  ac_stuff="$5"

  save_ext_shared=$ext_shared
  ext_shared=yes
  PHP_EVAL_LIBLINE([$]ac_stuff, LDFLAGS)
  AC_CHECK_LIB([$1],[$2],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    $3
  ],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    unset ac_cv_lib_$1[]_$2
    $4
  ])dnl
])';
            file_put_contents(SOURCE_PATH . '/php-src/ext/curl/config.m4', $file1 . "\n" . $files . "\n" . $file2);
        }

        // if ($builder->getExt('pdo_sqlite')) {
        //    FileSystem::replaceFile()
        // }
    }

    public static function patchSwow(): bool
    {
        if (Util::getPHPVersionID() >= 80000) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D swow swow-src\ext');
            } else {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s swow-src/ext swow');
            }
            return true;
        }
        return false;
    }

    public static function patchPHPConfigure(BuilderBase $builder): void
    {
        $frameworks = $builder instanceof MacOSBuilder ? ' ' . $builder->getFrameworks(true) . ' ' : '';
        $patch = [];
        if ($curl = $builder->getExt('curl')) {
            $patch[] = ['curl check', '/-lcurl/', $curl->getLibFilesString() . $frameworks];
        }
        if ($bzip2 = $builder->getExt('bz2')) {
            $patch[] = ['bzip2 check', '/-lbz2/', $bzip2->getLibFilesString() . $frameworks];
        }
        if ($pdo_sqlite = $builder->getExt('pdo_sqlite')) {
            $patch[] = ['pdo_sqlite linking', '/sqlite3_column_table_name=yes/', 'sqlite3_column_table_name=no'];
        }
        if ($event = $builder->getExt('event')) {
            $patch[] = ['event check', '/-levent_openssl/', $event->getLibFilesString()];
        }
        if ($readline = $builder->getExt('readline')) {
            $patch[] = ['readline patch', '/-lncurses/', $readline->getLibFilesString()];
        }
        $patch[] = ['disable capstone', '/have_capstone="yes"/', 'have_capstone="no"'];
        foreach ($patch as $item) {
            logger()->info('Patching configure: ' . $item[0]);
            FileSystem::replaceFile(SOURCE_PATH . '/php-src/configure', REPLACE_FILE_PREG, $item[1], $item[2]);
        }
    }

    public static function patchUnixLibpng(): void
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/libpng/configure',
            REPLACE_FILE_STR,
            '-lz',
            BUILD_LIB_PATH . '/libz.a'
        );
    }

    public static function patchCurlMacOS(): void
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/curl/CMakeLists.txt',
            REPLACE_FILE_PREG,
            '/NOT COREFOUNDATION_FRAMEWORK/m',
            'FALSE'
        );
        FileSystem::replaceFile(
            SOURCE_PATH . '/curl/CMakeLists.txt',
            REPLACE_FILE_PREG,
            '/NOT SYSTEMCONFIGURATION_FRAMEWORK/m',
            'FALSE'
        );
    }

    public static function patchMicro(): bool
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

        $patch_list = [
            'static_opcache',
            'static_extensions_win32',
            'cli_checks',
            'disable_huge_page',
            'vcruntime140',
            'win32',
            'zend_stream',
        ];
        $patch_list = array_merge($patch_list, match (PHP_OS_FAMILY) {
            'Windows' => [
                'cli_static',
            ],
            'Darwin' => [
                'macos_iconv',
            ],
            default => [],
        });
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
            (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . $patchesStr . ' | patch -p1'
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

    public static function patchPHPAfterConfigure(BuilderBase $param)
    {
        if ($param instanceof LinuxBuilder) {
            FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCPY 1$/m', '');
            FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCAT 1$/m', '');
        }
        FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_OPENPTY 1$/m', '');

        // patch openssl3 with php8.0 bug
        if (file_exists(SOURCE_PATH . '/openssl/VERSION.dat') && Util::getPHPVersionID() >= 80000 && Util::getPHPVersionID() < 80100) {
            $openssl_c = file_get_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c');
            $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
            file_put_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c', $openssl_c);
        }
    }
}
