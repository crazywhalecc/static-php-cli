<?php

declare(strict_types=1);

namespace SPC\store;

use SPC\exception\WrongUsageException;

class Config
{
    public static ?array $pkg = null;

    public static ?array $source = null;

    public static ?array $lib = null;

    public static ?array $ext = null;

    public static ?array $pre_built = null;

    /**
     * Get pre-built configuration by name
     *
     * @param  string $name The name of the pre-built configuration
     * @return mixed  The pre-built configuration or null if not found
     */
    public static function getPreBuilt(string $name): mixed
    {
        if (self::$pre_built === null) {
            self::$pre_built = FileSystem::loadConfigArray('pre-built');
        }
        $supported_sys_based = ['match-pattern', 'prefer-stable', 'repo'];
        if (in_array($name, $supported_sys_based)) {
            $m_key = match (PHP_OS_FAMILY) {
                'Windows' => ['-windows', '-win', ''],
                'Darwin' => ['-macos', '-unix', ''],
                'Linux' => ['-linux', '-unix', ''],
                'BSD' => ['-freebsd', '-bsd', '-unix', ''],
                default => throw new WrongUsageException('OS ' . PHP_OS_FAMILY . ' is not supported'),
            };
            foreach ($m_key as $v) {
                if (isset(self::$pre_built["{$name}{$v}"])) {
                    return self::$pre_built["{$name}{$v}"];
                }
            }
        }
        return self::$pre_built[$name] ?? null;
    }

    /**
     * Get source configuration by name
     *
     * @param  string     $name The name of the source
     * @return null|array The source configuration or null if not found
     */
    public static function getSource(string $name): ?array
    {
        if (self::$source === null) {
            self::$source = FileSystem::loadConfigArray('source');
        }
        return self::$source[$name] ?? null;
    }

    /**
     * Get package configuration by name
     *
     * @param  string     $name The name of the package
     * @return null|array The package configuration or null if not found
     */
    public static function getPkg(string $name): ?array
    {
        if (self::$pkg === null) {
            self::$pkg = FileSystem::loadConfigArray('pkg');
        }
        return self::$pkg[$name] ?? null;
    }

    /**
     * Get library configuration by name and optional key
     * Supports platform-specific configurations for different operating systems
     *
     * @param  string      $name    The name of the library
     * @param  null|string $key     The configuration key (static-libs, headers, lib-depends, lib-suggests, frameworks, bin)
     * @param  mixed       $default Default value if key not found
     * @return mixed       The library configuration or default value
     */
    public static function getLib(string $name, ?string $key = null, mixed $default = null)
    {
        if (self::$lib === null) {
            self::$lib = FileSystem::loadConfigArray('lib');
        }
        if (!isset(self::$lib[$name])) {
            throw new WrongUsageException('lib [' . $name . '] is not supported yet');
        }
        $supported_sys_based = ['static-libs', 'headers', 'lib-depends', 'lib-suggests', 'frameworks', 'bin'];
        if ($key !== null && in_array($key, $supported_sys_based)) {
            $m_key = match (PHP_OS_FAMILY) {
                'Windows' => ['-windows', '-win', ''],
                'Darwin' => ['-macos', '-unix', ''],
                'Linux' => ['-linux', '-unix', ''],
                'BSD' => ['-freebsd', '-bsd', '-unix', ''],
                default => throw new WrongUsageException('OS ' . PHP_OS_FAMILY . ' is not supported'),
            };
            foreach ($m_key as $v) {
                if (isset(self::$lib[$name][$key . $v])) {
                    return self::$lib[$name][$key . $v];
                }
            }
            return $default;
        }
        if ($key !== null) {
            return self::$lib[$name][$key] ?? $default;
        }
        return self::$lib[$name];
    }

    /**
     * Get all library configurations
     *
     * @return array All library configurations
     */
    public static function getLibs(): array
    {
        if (self::$lib === null) {
            self::$lib = FileSystem::loadConfigArray('lib');
        }
        return self::$lib;
    }

    /**
     * Get extension target configuration by name
     *
     * @param  string     $name The name of the extension
     * @return null|array The extension target configuration or default ['static', 'shared']
     */
    public static function getExtTarget(string $name): ?array
    {
        if (self::$ext === null) {
            self::$ext = FileSystem::loadConfigArray('ext');
        }
        if (!isset(self::$ext[$name])) {
            throw new WrongUsageException('ext [' . $name . '] is not supported yet');
        }
        return self::$ext[$name]['target'] ?? ['static', 'shared'];
    }

    /**
     * Get extension configuration by name and optional key
     * Supports platform-specific configurations for different operating systems
     *
     * @param  string      $name    The name of the extension
     * @param  null|string $key     The configuration key (lib-depends, lib-suggests, ext-depends, ext-suggests, arg-type)
     * @param  mixed       $default Default value if key not found
     * @return mixed       The extension configuration or default value
     */
    public static function getExt(string $name, ?string $key = null, mixed $default = null)
    {
        if (self::$ext === null) {
            self::$ext = FileSystem::loadConfigArray('ext');
        }
        if (!isset(self::$ext[$name])) {
            throw new WrongUsageException('ext [' . $name . '] is not supported yet');
        }
        $supported_sys_based = ['lib-depends', 'lib-suggests', 'ext-depends', 'ext-suggests', 'arg-type'];
        if ($key !== null && in_array($key, $supported_sys_based)) {
            $m_key = match (PHP_OS_FAMILY) {
                'Windows' => ['-windows', '-win', ''],
                'Darwin' => ['-macos', '-unix', ''],
                'Linux' => ['-linux', '-unix', ''],
                'BSD' => ['-freebsd', '-bsd', '-unix', ''],
                default => throw new WrongUsageException('OS ' . PHP_OS_FAMILY . ' is not supported'),
            };
            foreach ($m_key as $v) {
                if (isset(self::$ext[$name][$key . $v])) {
                    return self::$ext[$name][$key . $v];
                }
            }
            return $default;
        }
        if ($key !== null) {
            return self::$ext[$name][$key] ?? $default;
        }
        return self::$ext[$name];
    }

    /**
     * Get all extension configurations
     *
     * @return array All extension configurations
     */
    public static function getExts(): array
    {
        if (self::$ext === null) {
            self::$ext = FileSystem::loadConfigArray('ext');
        }
        return self::$ext;
    }

    /**
     * Get all source configurations
     *
     * @return array All source configurations
     */
    public static function getSources(): array
    {
        if (self::$source === null) {
            self::$source = FileSystem::loadConfigArray('source');
        }
        return self::$source;
    }
}
