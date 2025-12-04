<?php

declare(strict_types=1);

namespace StaticPHP\Registry;

use StaticPHP\Artifact\ArtifactLoader;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\ConsoleApplication;
use StaticPHP\Doctor\DoctorLoader;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\PackageLoader;
use StaticPHP\Util\FileSystem;
use Symfony\Component\Yaml\Yaml;

class Registry
{
    /** @var string[] List of loaded registry names */
    private static array $loaded_registries = [];

    /**
     * Load a registry from file path.
     * This method handles external registries that may not be in composer autoload.
     *
     * @param string $registry_file Path to registry file (json or yaml)
     * @param bool   $auto_require  Whether to auto-require PHP files (for external plugins)
     */
    public static function loadRegistry(string $registry_file, bool $auto_require = true): void
    {
        $yaml = file_get_contents($registry_file);
        if ($yaml === false) {
            throw new EnvironmentException("Failed to read registry file: {$registry_file}");
        }
        $data = match (pathinfo($registry_file, PATHINFO_EXTENSION)) {
            'json' => json_decode($yaml, true),
            'yaml', 'yml' => Yaml::parse($yaml),
            default => throw new EnvironmentException("Unsupported registry file format: {$registry_file}"),
        };
        if (!is_array($data)) {
            throw new EnvironmentException("Invalid registry format in file: {$registry_file}");
        }
        $registry_name = $data['name'] ?? null;
        if (!is_string($registry_name) || empty($registry_name)) {
            throw new EnvironmentException("Registry 'name' is missing or invalid in file: {$registry_file}");
        }

        // Prevent loading the same registry twice
        if (in_array($registry_name, self::$loaded_registries, true)) {
            logger()->debug("Registry '{$registry_name}' already loaded, skipping.");
            return;
        }
        self::$loaded_registries[] = $registry_name;

        logger()->debug("Loading registry '{$registry_name}' from file: {$registry_file}");

        // Load composer autoload if specified (for external registries with their own dependencies)
        if (isset($data['autoload']) && is_string($data['autoload'])) {
            $autoload_path = self::fullpath($data['autoload'], dirname($registry_file));
            if (file_exists($autoload_path)) {
                logger()->debug("Loading external autoload from: {$autoload_path}");
                require_once $autoload_path;
            } else {
                logger()->warning("Autoload file not found: {$autoload_path}");
            }
        }

        // load doctor items from PSR-4 directories
        if (isset($data['doctor']['psr-4']) && is_assoc_array($data['doctor']['psr-4'])) {
            foreach ($data['doctor']['psr-4'] as $namespace => $path) {
                $path = self::fullpath($path, dirname($registry_file));
                DoctorLoader::loadFromPsr4Dir($path, $namespace, $auto_require);
            }
        }

        // load doctor items from specific classes
        // Supports both array format ["ClassName"] and map format {"ClassName": "path/to/file.php"}
        if (isset($data['doctor']['classes']) && is_array($data['doctor']['classes'])) {
            foreach ($data['doctor']['classes'] as $key => $value) {
                [$class, $file] = self::parseClassEntry($key, $value);
                self::requireClassFile($class, $file, dirname($registry_file), $auto_require);
                DoctorLoader::loadFromClass($class);
            }
        }

        // load package configs
        if (isset($data['package']['config']) && is_array($data['package']['config'])) {
            foreach ($data['package']['config'] as $path) {
                $path = self::fullpath($path, dirname($registry_file));
                if (is_file($path)) {
                    PackageConfig::loadFromFile($path);
                } elseif (is_dir($path)) {
                    PackageConfig::loadFromDir($path);
                }
            }
        }

        // load artifact configs
        if (isset($data['artifact']['config']) && is_array($data['artifact']['config'])) {
            foreach ($data['artifact']['config'] as $path) {
                $path = self::fullpath($path, dirname($registry_file));
                if (is_file($path)) {
                    ArtifactConfig::loadFromFile($path);
                } elseif (is_dir($path)) {
                    ArtifactConfig::loadFromDir($path);
                }
            }
        }

        // load packages from PSR-4 directories
        if (isset($data['package']['psr-4']) && is_assoc_array($data['package']['psr-4'])) {
            foreach ($data['package']['psr-4'] as $namespace => $path) {
                $path = self::fullpath($path, dirname($registry_file));
                PackageLoader::loadFromPsr4Dir($path, $namespace, $auto_require);
            }
        }

        // load packages from specific classes
        // Supports both array format ["ClassName"] and map format {"ClassName": "path/to/file.php"}
        if (isset($data['package']['classes']) && is_array($data['package']['classes'])) {
            foreach ($data['package']['classes'] as $key => $value) {
                [$class, $file] = self::parseClassEntry($key, $value);
                self::requireClassFile($class, $file, dirname($registry_file), $auto_require);
                PackageLoader::loadFromClass($class);
            }
        }

        // load artifacts from PSR-4 directories
        if (isset($data['artifact']['psr-4']) && is_assoc_array($data['artifact']['psr-4'])) {
            foreach ($data['artifact']['psr-4'] as $namespace => $path) {
                $path = self::fullpath($path, dirname($registry_file));
                ArtifactLoader::loadFromPsr4Dir($path, $namespace, $auto_require);
            }
        }

        // load artifacts from specific classes
        // Supports both array format ["ClassName"] and map format {"ClassName": "path/to/file.php"}
        if (isset($data['artifact']['classes']) && is_array($data['artifact']['classes'])) {
            foreach ($data['artifact']['classes'] as $key => $value) {
                [$class, $file] = self::parseClassEntry($key, $value);
                self::requireClassFile($class, $file, dirname($registry_file), $auto_require);
                ArtifactLoader::loadFromClass($class);
            }
        }

        // load additional commands from PSR-4 directories
        if (isset($data['command']['psr-4']) && is_assoc_array($data['command']['psr-4'])) {
            foreach ($data['command']['psr-4'] as $namespace => $path) {
                $path = self::fullpath($path, dirname($registry_file));
                $classes = FileSystem::getClassesPsr4($path, $namespace, auto_require: $auto_require);
                $instances = array_map(fn ($x) => new $x(), $classes);
                ConsoleApplication::_addAdditionalCommands($instances);
            }
        }

        // load additional commands from specific classes
        // Supports both array format ["ClassName"] and map format {"ClassName": "path/to/file.php"}
        if (isset($data['command']['classes']) && is_array($data['command']['classes'])) {
            $instances = [];
            foreach ($data['command']['classes'] as $key => $value) {
                [$class, $file] = self::parseClassEntry($key, $value);
                self::requireClassFile($class, $file, dirname($registry_file), $auto_require);
                $instances[] = new $class();
            }
            ConsoleApplication::_addAdditionalCommands($instances);
        }
    }

    /**
     * Load registries from environment variable or CLI option.
     * Supports comma-separated list of registry file paths.
     *
     * @param null|string $registries Comma-separated registry paths, or null to read from SPC_REGISTRIES env
     */
    public static function loadFromEnvOrOption(?string $registries = null): void
    {
        $registries ??= getenv('SPC_REGISTRIES') ?: null;

        if ($registries === null) {
            return;
        }

        $paths = array_filter(array_map('trim', explode(':', $registries)));
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                logger()->warning("Registry file not found: {$path}");
                continue;
            }
            self::loadRegistry($path);
        }
    }

    /**
     * Get list of loaded registry names.
     *
     * @return string[]
     */
    public static function getLoadedRegistries(): array
    {
        return self::$loaded_registries;
    }

    /**
     * Reset loaded registries (for testing).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$loaded_registries = [];
    }

    /**
     * Parse a class entry from the classes array.
     * Supports two formats:
     * - Array format: ["ClassName"] where key is numeric and value is class name
     * - Map format: {"ClassName": "path/to/file.php"} where key is class name and value is file path
     *
     * @param  int|string             $key   Array key (numeric for array format, class name for map format)
     * @param  string                 $value Array value (class name for array format, file path for map format)
     * @return array{string, ?string} [class_name, file_path or null]
     */
    private static function parseClassEntry(int|string $key, string $value): array
    {
        if (is_int($key)) {
            // Array format: ["ClassName"] - value is the class name, no file path
            return [$value, null];
        }
        // Map format: {"ClassName": "path/to/file.php"} - key is class name, value is file path
        return [$key, $value];
    }

    /**
     * Require a class file if the class doesn't exist and auto_require is enabled.
     *
     * @param string      $class        Full class name
     * @param null|string $file_path    File path (relative or absolute), null if not provided
     * @param string      $base_path    Base path for relative paths
     * @param bool        $auto_require Whether to auto-require
     */
    private static function requireClassFile(string $class, ?string $file_path, string $base_path, bool $auto_require): void
    {
        if (!$auto_require || class_exists($class)) {
            return;
        }

        // If file path is provided, require it
        if ($file_path !== null) {
            $full_path = self::fullpath($file_path, $base_path);
            require_once $full_path;
            return;
        }

        // Class not found and no file path provided
        throw new EnvironmentException(
            "Class '{$class}' not found. For external registries, either:\n" .
            "  1. Add an 'autoload' entry pointing to your composer autoload file\n" .
            "  2. Use 'psr-4' instead of 'classes' for auto-discovery\n" .
            "  3. Provide file path in classes map: \"{$class}\": \"path/to/file.php\""
        );
    }

    /**
     * Return full path, resolving relative paths against a base path.
     *
     * @param string $path               Input path (relative or absolute)
     * @param string $relative_path_base Base path for relative paths
     */
    private static function fullpath(string $path, string $relative_path_base): string
    {
        if (FileSystem::isRelativePath($path)) {
            $path = $relative_path_base . DIRECTORY_SEPARATOR . $path;
        }
        if (!file_exists($path)) {
            throw new EnvironmentException("Path does not exist: {$path}");
        }
        return FileSystem::convertPath($path);
    }
}
