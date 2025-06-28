<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\linux\SystemUtil;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

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
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function init(): void
    {
        // Check pre-defined env vars exists
        if (getenv('BUILD_ROOT_PATH') === false) {
            throw new RuntimeException('You must include src/globals/internal-env.php before using GlobalEnvManager');
        }

        // Define env vars for unix
        if (is_unix()) {
            self::addPathIfNotExists(BUILD_BIN_PATH);
            self::putenv('PKG_CONFIG=' . BUILD_BIN_PATH . '/pkg-config');
            self::putenv('PKG_CONFIG_PATH=' . BUILD_ROOT_PATH . '/lib/pkgconfig');
        }

        $ini = self::readIniFile();

        $default_put_list = [];
        foreach ($ini['global'] as $k => $v) {
            if (getenv($k) === false) {
                $default_put_list[$k] = $v;
                self::putenv("{$k}={$v}");
            }
        }
        $os_ini = match (PHP_OS_FAMILY) {
            'Windows' => $ini['windows'] ?? [],
            'Darwin' => $ini['macos'] ?? [],
            'Linux' => $ini['linux'] ?? [],
            'BSD' => $ini['freebsd'] ?? [],
            default => [],
        };
        foreach ($os_ini as $k => $v) {
            if (getenv($k) === false) {
                $default_put_list[$k] = $v;
                self::putenv("{$k}={$v}");
            }
        }

        // deprecated: convert SPC_LIBC to SPC_TARGET
        if (getenv('SPC_LIBC') !== false) {
            logger()->warning('SPC_LIBC is deprecated, please use SPC_TARGET instead.');
            $target = match (getenv('SPC_LIBC')) {
                'musl' => SPCTarget::MUSL_STATIC,
                default => SPCTarget::GLIBC,
            };
            self::putenv("SPC_TARGET={$target}");
            self::putenv('SPC_LIBC');
        }

        // auto-select toolchain based on target and OS temporarily
        // TODO: use 'zig' instead of 'gcc-native' when ZigToolchain is implemented
        $toolchain = match (getenv('SPC_TARGET')) {
            SPCTarget::MUSL_STATIC, SPCTarget::MUSL => SystemUtil::isMuslDist() ? 'gcc-native' : 'musl',
            SPCTarget::MACHO => 'clang-native',
            SPCTarget::MSVC_STATIC => 'msvc',
            default => 'gcc-native',
        };

        SPCTarget::initTargetForToolchain($toolchain);

        // apply second time
        $ini2 = self::readIniFile();

        foreach ($ini2['global'] as $k => $v) {
            if (isset($default_put_list[$k]) && $default_put_list[$k] !== $v) {
                self::putenv("{$k}={$v}");
            }
        }
        $os_ini2 = match (PHP_OS_FAMILY) {
            'Windows' => $ini2['windows'] ?? [],
            'Darwin' => $ini2['macos'] ?? [],
            'Linux' => $ini2['linux'] ?? [],
            'BSD' => $ini2['freebsd'] ?? [],
            default => [],
        };
        foreach ($os_ini2 as $k => $v) {
            if (isset($default_put_list[$k]) && $default_put_list[$k] !== $v) {
                self::putenv("{$k}={$v}");
            }
        }
    }

    public static function putenv(string $val): void
    {
        f_putenv($val);
        self::$env_cache[] = $val;
    }

    public static function addPathIfNotExists(string $path): void
    {
        if (is_unix() && !str_contains(getenv('PATH'), $path)) {
            self::putenv("PATH={$path}:" . getenv('PATH'));
        }
    }

    public static function afterInit(): void
    {
        SPCTarget::afterInitTargetForToolchain();
    }

    /**
     * @throws WrongUsageException
     */
    private static function readIniFile(): array
    {
        // Init env.ini file, read order:
        //      WORKING_DIR/config/env.ini
        //      ROOT_DIR/config/env.ini
        $ini_files = [
            WORKING_DIR . '/config/env.ini',
            ROOT_DIR . '/config/env.ini',
        ];
        $ini_custom = [
            WORKING_DIR . '/config/env.custom.ini',
            ROOT_DIR . '/config/env.custom.ini',
        ];
        $ini = null;
        foreach ($ini_files as $ini_file) {
            if (file_exists($ini_file)) {
                $ini = parse_ini_file($ini_file, true);
                break;
            }
        }
        if ($ini === null) {
            throw new WrongUsageException('env.ini not found');
        }
        if ($ini === false || !isset($ini['global'])) {
            throw new WrongUsageException('Failed to parse ' . $ini_file);
        }
        // apply custom env
        foreach ($ini_custom as $ini_file) {
            if (file_exists($ini_file)) {
                $ini_custom = parse_ini_file($ini_file, true);
                if ($ini_custom !== false) {
                    $ini['global'] = array_merge($ini['global'], $ini_custom['global'] ?? []);
                    match (PHP_OS_FAMILY) {
                        'Windows' => $ini['windows'] = array_merge($ini['windows'], $ini_custom['windows'] ?? []),
                        'Darwin' => $ini['macos'] = array_merge($ini['macos'], $ini_custom['macos'] ?? []),
                        'Linux' => $ini['linux'] = array_merge($ini['linux'], $ini_custom['linux'] ?? []),
                        'BSD' => $ini['freebsd'] = array_merge($ini['freebsd'], $ini_custom['freebsd'] ?? []),
                        default => null,
                    };
                }
                break;
            }
        }
        return $ini;
    }
}
