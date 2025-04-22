<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\BuilderBase;
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
     * @param  null|BuilderBase    $builder Builder
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function init(?BuilderBase $builder = null): void
    {
        // Check pre-defined env vars exists
        if (getenv('BUILD_ROOT_PATH') === false) {
            throw new RuntimeException('You must include src/globals/internal-env.php before using GlobalEnvManager');
        }

        // Define env vars for unix
        if (is_unix()) {
            self::putenv('PATH=' . BUILD_ROOT_PATH . '/bin:' . getenv('PATH'));
            self::putenv('PKG_CONFIG=' . BUILD_BIN_PATH . '/pkg-config');
            self::putenv('PKG_CONFIG_PATH=' . BUILD_ROOT_PATH . '/lib/pkgconfig');
        }

        // Define env vars for linux
        if (PHP_OS_FAMILY === 'Linux') {
            $arch = getenv('GNU_ARCH');
            if (SystemUtil::isMuslDist()) {
                self::putenv('SPC_LINUX_DEFAULT_CC=gcc');
                self::putenv('SPC_LINUX_DEFAULT_CXX=g++');
                self::putenv('SPC_LINUX_DEFAULT_AR=ar');
            } else {
                self::putenv("SPC_LINUX_DEFAULT_CC={$arch}-linux-musl-gcc");
                self::putenv("SPC_LINUX_DEFAULT_CXX={$arch}-linux-musl-g++");
                self::putenv("SPC_LINUX_DEFAULT_AR={$arch}-linux-musl-ar");
            }
        }

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
        self::applyConfig($ini['global']);
        match (PHP_OS_FAMILY) {
            'Windows' => self::applyConfig($ini['windows']),
            'Darwin' => self::applyConfig($ini['macos']),
            'Linux' => self::applyConfig($ini['linux']),
            'BSD' => self::applyConfig($ini['freebsd']),
            default => null,
        };
    }

    public static function putenv(string $val): void
    {
        f_putenv($val);
        self::$env_cache[] = $val;
    }

    private static function applyConfig(array $ini): void
    {
        foreach ($ini as $k => $v) {
            if (getenv($k) === false) {
                self::putenv($k . '=' . $v);
            }
        }
    }
}
