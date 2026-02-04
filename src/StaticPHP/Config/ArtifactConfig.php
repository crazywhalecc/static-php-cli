<?php

declare(strict_types=1);

namespace StaticPHP\Config;

use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Registry\Registry;
use Symfony\Component\Yaml\Yaml;

class ArtifactConfig
{
    private static array $artifact_configs = [];

    public static function loadFromDir(string $dir, string $registry_name): array
    {
        if (!is_dir($dir)) {
            throw new WrongUsageException("Directory {$dir} does not exist, cannot load artifact config.");
        }
        $loaded = [];
        $files = glob("{$dir}/*");
        if (is_array($files)) {
            foreach ($files as $file) {
                self::loadFromFile($file, $registry_name);
                $loaded[] = $file;
            }
        }
        if (file_exists("{$dir}/artifact.json")) {
            self::loadFromFile("{$dir}/artifact.json", $registry_name);
            $loaded[] = "{$dir}/artifact.json";
        }
        return $loaded;
    }

    /**
     * Load artifact configurations from a specified JSON file.
     */
    public static function loadFromFile(string $file, string $registry_name): string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new WrongUsageException("Failed to read artifact config file: {$file}");
        }
        $data = match (pathinfo($file, PATHINFO_EXTENSION)) {
            'json' => json_decode($content, true),
            'yml', 'yaml' => Yaml::parse($content),
            default => throw new WrongUsageException("Unsupported artifact config file format: {$file}"),
        };
        if (!is_array($data)) {
            throw new WrongUsageException("Invalid JSON format in artifact config file: {$file}");
        }
        ConfigValidator::validateAndLintArtifacts(basename($file), $data);
        foreach ($data as $artifact_name => $config) {
            self::$artifact_configs[$artifact_name] = $config;
            Registry::_bindArtifactConfigFile($artifact_name, $registry_name, $file);
        }
        return $file;
    }

    /**
     * Get all loaded artifact configurations.
     *
     * @return array<string, array> an associative array of artifact configurations
     */
    public static function getAll(): array
    {
        return self::$artifact_configs;
    }

    /**
     * Get the configuration for a specific artifact by name.
     *
     * @param  string     $artifact_name the name of the artifact
     * @return null|array the configuration array for the specified artifact, or null if not found
     */
    public static function get(string $artifact_name): ?array
    {
        return self::$artifact_configs[$artifact_name] ?? null;
    }

    /**
     * Register an inline artifact configuration.
     * Used when artifact is defined inline within a package configuration.
     *
     * @param string $artifact_name Artifact name (usually same as package name)
     * @param array  $config        Artifact configuration
     * @param string $registry_name Registry name
     * @param string $source_info   Source info for debugging
     */
    public static function registerInlineArtifact(string $artifact_name, array $config, string $registry_name, string $source_info = 'inline'): void
    {
        self::$artifact_configs[$artifact_name] = $config;
        Registry::_bindArtifactConfigFile($artifact_name, $registry_name, $source_info);
    }
}
