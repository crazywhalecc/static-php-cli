<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\BuilderBase;
use SPC\builder\freebsd\SystemUtil as BSDSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacOSSystemUtil;
use SPC\builder\windows\SystemUtil as WindowsSystemUtil;
use SPC\exception\RuntimeException;

/**
 * Environment variable manager
 */
class GlobalEnvManager
{
    private static array $env_cache = [];

    public static function getInitializedEnv(): array
    {
        return self::$env_cache;
    }

    /**
     * Initialize the environment variables
     *
     * @param  BuilderBase      $builder Builder
     * @throws RuntimeException
     */
    public static function init(BuilderBase $builder): void
    {
        // Init global env, build related path
        self::putenv('BUILD_ROOT_PATH=' . BUILD_ROOT_PATH);
        self::putenv('BUILD_INCLUDE_PATH=' . BUILD_INCLUDE_PATH);
        self::putenv('BUILD_LIB_PATH=' . BUILD_LIB_PATH);
        self::putenv('BUILD_BIN_PATH=' . BUILD_BIN_PATH);
        self::putenv('PKG_ROOT_PATH=' . PKG_ROOT_PATH);
        self::putenv('SOURCE_PATH=' . SOURCE_PATH);
        self::putenv('DOWNLOAD_PATH=' . DOWNLOAD_PATH);

        // Init SPC env
        self::initIfNotExists('SPC_CONCURRENCY', match (PHP_OS_FAMILY) {
            'Windows' => (string) WindowsSystemUtil::getCpuCount(),
            'Darwin' => (string) MacOSSystemUtil::getCpuCount(),
            'Linux' => (string) LinuxSystemUtil::getCpuCount(),
            'BSD' => (string) BSDSystemUtil::getCpuCount(),
            default => '1',
        });

        // Init system-specific env
        match (PHP_OS_FAMILY) {
            'Windows' => self::initWindowsEnv(),
            'Darwin' => self::initDarwinEnv($builder),
            'Linux' => self::initLinuxEnv($builder),
            'BSD' => 'TODO',
            default => logger()->warning('Unknown OS: ' . PHP_OS_FAMILY),
        };
    }

    private static function initWindowsEnv(): void
    {
        // Windows need php-sdk binary tools
        self::initIfNotExists('PHP_SDK_PATH', WORKING_DIR . DIRECTORY_SEPARATOR . 'php-sdk-binary-tools');
        self::initIfNotExists('UPX_EXEC', PKG_ROOT_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'upx.exe');
        self::initIfNotExists('SPC_MICRO_PATCHES', 'static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,cli_static');
    }

    private static function initLinuxEnv(BuilderBase $builder): void
    {
        // Init C Compiler and C++ Compiler (alpine)
        if (LinuxSystemUtil::isMuslDist()) {
            self::initIfNotExists('CC', 'gcc');
            self::initIfNotExists('CXX', 'g++');
            self::initIfNotExists('AR', 'ar');
            self::initIfNotExists('LD', 'ld.gold');
        } else {
            $arch = arch2gnu(php_uname('m'));
            self::initIfNotExists('CC', "{$arch}-linux-musl-gcc");
            self::initIfNotExists('CXX', "{$arch}-linux-musl-g++");
            self::initIfNotExists('AR', "{$arch}-linux-musl-ar");
            self::initIfNotExists('LD', 'ld.gold');
            if (getenv('SPC_NO_MUSL_PATH') !== 'yes') {
                self::putenv("PATH=/usr/local/musl/bin:/usr/local/musl/{$arch}-linux-musl/bin:" . getenv('PATH'));
            }
        }

        // Init arch-specific cflags
        self::initIfNotExists('SPC_DEFAULT_C_FLAGS', '');
        self::initIfNotExists('SPC_DEFAULT_CXX_FLAGS', '');
        self::initIfNotExists('SPC_EXTRA_LIBS', '');

        // SPC_MICRO_PATCHES for linux
        self::initIfNotExists('SPC_MICRO_PATCHES', 'static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream');

        // Init linux-only env
        self::initIfNotExists('UPX_EXEC', PKG_ROOT_PATH . '/bin/upx');
        self::initIfNotExists('GNU_ARCH', arch2gnu(php_uname('m')));

        // optimization flags with different strip option
        $php_extra_cflags_optimize = $builder->getOption('no-strip') ? '-g -O0' : '-g -Os';
        // optimization flags with different c compiler
        $clang_use_lld = str_ends_with(getenv('CC'), 'clang') && LinuxSystemUtil::findCommand('lld') ? '-Xcompiler -fuse-ld=lld ' : '';

        $init_spc_cmd_maps = [
            // Init default build command prefix
            'SPC_CMD_PREFIX_PHP_BUILDCONF' => './buildconf --force',
            'SPC_CMD_PREFIX_PHP_CONFIGURE' => $builder->getOption('ld_library_path') . ' ./configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg',
            'SPC_CMD_PREFIX_PHP_MAKE' => 'make -j' . getenv('SPC_CONCURRENCY'),
            // Init default build vars for build command
            'SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS' => getenv('SPC_DEFAULT_C_FLAGS'),
            'SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH,
            'SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS' => '-L' . BUILD_LIB_PATH,
            'SPC_CMD_VAR_PHP_CONFIGURE_LIBS' => '-ldl -lpthread -lm',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS' => $php_extra_cflags_optimize . ' -fno-ident -fPIE',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS' => '',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM' => $clang_use_lld . '-all-static',
        ];
        foreach ($init_spc_cmd_maps as $name => $value) {
            self::initIfNotExists($name, $value);
        }

        self::initUnixEnv($builder);
    }

    private static function initDarwinEnv(BuilderBase $builder): void
    {
        // Init C Compiler and C++ Compiler
        self::initIfNotExists('CC', 'clang');
        self::initIfNotExists('CXX', 'clang++');

        // Init arch-specific cflags
        self::initIfNotExists('SPC_DEFAULT_C_FLAGS', match (php_uname('m')) {
            'arm64', 'aarch64' => '--target=arm64-apple-darwin',
            default => '--target=x86_64-apple-darwin',
        });
        // Init arch-specific cxxflags
        self::initIfNotExists('SPC_DEFAULT_CXX_FLAGS', match (php_uname('m')) {
            'arm64', 'aarch64' => '--target=arm64-apple-darwin',
            default => '--target=x86_64-apple-darwin',
        });

        // Init extra libs (will be appended before `before-php-buildconf` event point)
        self::initIfNotExists('SPC_EXTRA_LIBS', '');

        // SPC_MICRO_PATCHES for macOS
        self::initIfNotExists('SPC_MICRO_PATCHES', 'static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,macos_iconv');

        $init_spc_cmd_maps = [
            // Init default build command prefix
            'SPC_CMD_PREFIX_PHP_BUILDCONF' => './buildconf --force',
            'SPC_CMD_PREFIX_PHP_CONFIGURE' => './configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg',
            'SPC_CMD_PREFIX_PHP_MAKE' => 'make -j' . getenv('SPC_CONCURRENCY'),
            // Init default build vars for build command
            'SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS' => getenv('SPC_DEFAULT_C_FLAGS') . ' -Werror=unknown-warning-option',
            'SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH,
            'SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS' => '-L' . BUILD_LIB_PATH,
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS' => $builder->getOption('no-strip') ? '-g -O0' : '-g -Os',
            'SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS' => '-lresolv',
        ];
        foreach ($init_spc_cmd_maps as $name => $value) {
            self::initIfNotExists($name, $value);
        }

        self::initUnixEnv($builder);
    }

    private static function initUnixEnv(BuilderBase $builder): void
    {
        self::putenv('PATH=' . BUILD_ROOT_PATH . '/bin:' . getenv('PATH'));
        self::putenv('PKG_CONFIG=' . BUILD_BIN_PATH . '/pkg-config');
        self::putenv('PKG_CONFIG_PATH=' . BUILD_ROOT_PATH . '/lib/pkgconfig');
    }

    /**
     * Initialize the environment variable if it does not exist
     *
     * @param string $name  Environment variable name
     * @param string $value Environment variable value
     */
    private static function initIfNotExists(string $name, string $value): void
    {
        if (($val = getenv($name)) === false) {
            self::putenv($name . '=' . $value);
        } else {
            logger()->debug("env [{$name}] existing: {$val}");
        }
    }

    private static function putenv(string $val): void
    {
        f_putenv($val);
        self::$env_cache[] = $val;
    }
}
