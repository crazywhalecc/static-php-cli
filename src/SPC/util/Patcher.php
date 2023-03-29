<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\BuilderBase;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class Patcher
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function patchPHPDepFiles(): void
    {
        $ver_file = SOURCE_PATH . '/php-src/main/php_version.h';
        if (!file_exists($ver_file)) {
            throw new FileSystemException('Patch failed, cannot find php source files');
        }
        $version_h = FileSystem::readFile(SOURCE_PATH . '/php-src/main/php_version.h');
        preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)\s+#\s*define\s+PHP_MINOR_VERSION\s+(\d+)\s+/m', $version_h, $match);
        // $ver = "{$match[1]}.{$match[2]}";

        logger()->info('Patching php');

        $major_ver = $match[1] . $match[2];
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
    }

    public static function patchOpenssl3(): void
    {
        logger()->info('Patching PHP with openssl 3.0');
        $openssl_c = file_get_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c');
        $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
        file_put_contents(SOURCE_PATH . '/php-src/ext/openssl/openssl.c', $openssl_c);
    }

    /**
     * @throws RuntimeException
     */
    public static function patchSwow(): void
    {
        logger()->info('Patching swow');
        if (PHP_OS_FAMILY === 'Windows') {
            f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D swow swow-src\ext');
        } else {
            f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s swow-src/ext swow');
        }
    }

    public static function patchPHPBeforeConfigure(BuilderBase $builder): void
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

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function patchPHPConfigure(BuilderBase $builder): void
    {
        $frameworks = $builder instanceof MacOSBuilder ? ' ' . $builder->getFrameworks(true) . ' ' : '';
        $curl = $builder->getExt('curl');
        if ($curl) {
            logger()->info('patching configure for curl checks');
            FileSystem::replaceFile(
                SOURCE_PATH . '/php-src/configure',
                REPLACE_FILE_PREG,
                '/-lcurl/',
                $curl->getLibFilesString() . $frameworks
            );
        }
        $bzip2 = $builder->getExt('bz2');
        if ($bzip2) {
            logger()->info('patching configure for bzip2 checks');
            FileSystem::replaceFile(
                SOURCE_PATH . '/php-src/configure',
                REPLACE_FILE_PREG,
                '/-lbz2/',
                $bzip2->getLibFilesString() . $frameworks
            );
        }
        $pdo_sqlite = $builder->getExt('pdo_sqlite');
        if ($pdo_sqlite) {
            logger()->info('patching configure for pdo_sqlite linking');
            FileSystem::replaceFile(
                SOURCE_PATH . '/php-src/configure',
                REPLACE_FILE_PREG,
                '/sqlite3_column_table_name=yes/',
                'sqlite3_column_table_name=no'
            );
        }
        logger()->info('patching configure for disable capstone');
        FileSystem::replaceFile(
            SOURCE_PATH . '/php-src/configure',
            REPLACE_FILE_PREG,
            '/have_capstone="yes"/',
            'have_capstone="no"'
        );
        if (property_exists($builder, 'arch') && php_uname('m') !== $builder->arch) {
            // cross-compiling
            switch ($builder->arch) {
                case 'aarch64':
                case 'arm64':
                    // almost all bsd/linux supports this
                    logger()->info('patching configure for shm mmap checks (cross-compiling)');
                    FileSystem::replaceFile(
                        SOURCE_PATH . '/php-src/configure',
                        REPLACE_FILE_PREG,
                        '/have_shm_mmap_anon=no/',
                        'have_shm_mmap_anon=yes'
                    );
                    FileSystem::replaceFile(
                        SOURCE_PATH . '/php-src/configure',
                        REPLACE_FILE_PREG,
                        '/have_shm_mmap_posix=no/',
                        'have_shm_mmap_posix=yes'
                    );
                    break;
                case 'x86_64':
                    break;
                default:
                    throw new RuntimeException('unsupported arch while patching php configure: ' . $builder->arch);
            }
        }
    }

    /**
     * @throws FileSystemException
     */
    public static function patchUnixLibpng(): void
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/libpng/configure',
            REPLACE_FILE_STR,
            '-lz',
            BUILD_LIB_PATH . '/libz.a'
        );
    }

    /**
     * @throws FileSystemException
     */
    public static function patchDarwinOpenssl11(): void
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/openssl/test/v3ext.c',
            REPLACE_FILE_STR,
            '#include <stdio.h>',
            '#include <stdio.h>' . PHP_EOL . '#include <string.h>'
        );
    }

    public static function patchLinuxPkgConfig(string $path): void
    {
        logger()->info("fixing pc {$path}");

        $workspace = BUILD_ROOT_PATH;
        if ($workspace === '/') {
            $workspace = '';
        }

        $content = file_get_contents($path);
        $content = preg_replace('/^prefix=.+$/m', "prefix={$workspace}", $content);
        $content = preg_replace('/^libdir=.+$/m', 'libdir=${prefix}/lib', $content);
        $content = preg_replace('/^includedir=.+$/m', 'includedir=${prefix}/include', $content);
        file_put_contents($path, $content);
    }

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public static function patchLinuxConfigHeader(string $libc): void
    {
        switch ($libc) {
            case 'musl_wrapper':
                // bad checks
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCPY 1$/m', '');
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_STRLCAT 1$/m', '');
                // no break
            case 'musl':
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_FUNC_ATTRIBUTE_IFUNC 1$/m', '');
                break;
            case 'glibc':
                // avoid lcrypt dependency
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_CRYPT 1$/m', '');
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_CRYPT_R 1$/m', '');
                FileSystem::replaceFile(SOURCE_PATH . '/php-src/main/php_config.h', REPLACE_FILE_PREG, '/^#define HAVE_CRYPT_H 1$/m', '');
                break;
            default:
                throw new RuntimeException('not implemented');
        }
    }
}
