<?php

declare(strict_types=1);

namespace StaticPHP\Config;

use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;

class PackageConfig
{
    private static array $package_configs = [];

    /**
     * Load package configurations from a specified directory.
     * It will look for files matching the pattern 'pkg.*.json' and 'pkg.json'.
     */
    public static function loadFromDir(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new WrongUsageException("Directory {$dir} does not exist, cannot load pkg.json config.");
        }
        $files = glob("{$dir}/pkg.*.json");
        if (is_array($files)) {
            foreach ($files as $file) {
                self::loadFromFile($file);
            }
        }
        if (file_exists("{$dir}/pkg.json")) {
            self::loadFromFile("{$dir}/pkg.json");
        }
    }

    /**
     * Load package configurations from a specified JSON file.
     *
     * @param string $file the path to the json package configuration file
     */
    public static function loadFromFile(string $file): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new WrongUsageException("Failed to read package config file: {$file}");
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new WrongUsageException("Invalid JSON format in package config file: {$file}");
        }
        ConfigValidator::validateAndLintPackages(basename($file), $data);
        foreach ($data as $pkg_name => $config) {
            self::$package_configs[$pkg_name] = $config;
        }
    }

    /**
     * Check if a package configuration exists.
     */
    public static function isPackageExists(string $pkg_name): bool
    {
        return isset(self::$package_configs[$pkg_name]);
    }

    public static function getAll(): array
    {
        return self::$package_configs;
    }

    /**
     * Get a specific field from a package configuration.
     *
     * @param  string      $pkg_name   Package name
     * @param  null|string $field_name Package config field name
     * @param  null|mixed  $default    Default value if field not found
     * @return mixed       The value of the specified field or the default value
     */
    public static function get(string $pkg_name, ?string $field_name = null, mixed $default = null): mixed
    {
        if (!self::isPackageExists($pkg_name)) {
            return $default;
        }
        // use suffixes to find field
        $suffixes = match (SystemTarget::getTargetOS()) {
            'Windows' => ['@windows', ''],
            'Darwin' => ['@macos', '@unix', ''],
            'Linux' => ['@linux', '@unix', ''],
            'BSD' => ['@freebsd', '@bsd', '@unix', ''],
        };
        if ($field_name === null) {
            return self::$package_configs[$pkg_name];
        }
        if (in_array($field_name, ConfigValidator::SUFFIX_ALLOWED_FIELDS)) {
            foreach ($suffixes as $suffix) {
                $suffixed_field = $field_name . $suffix;
                if (isset(self::$package_configs[$pkg_name][$suffixed_field])) {
                    return self::$package_configs[$pkg_name][$suffixed_field];
                }
            }
            return $default;
        }
        return self::$package_configs[$pkg_name][$field_name] ?? $default;
    }
}
