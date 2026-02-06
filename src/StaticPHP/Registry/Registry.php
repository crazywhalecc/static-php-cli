<?php

declare(strict_types=1);

namespace StaticPHP\Registry;

use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\ConsoleApplication;
use StaticPHP\Exception\RegistryException;
use StaticPHP\Util\FileSystem;
use Symfony\Component\Yaml\Yaml;

class Registry
{
    private static ?string $current_registry_name = null;

    /** @var string[] List of loaded registries */
    private static array $loaded_registries = [];

    /** @var array<string, array> Loaded registry configs */
    private static array $registry_configs = [];

    private static array $loaded_package_configs = [];

    private static array $loaded_artifact_configs = [];

    /** @var array<string, array{registry: string, config: string}> Maps of package and artifact names to their registry config file paths (for reverse lookup) */
    private static array $package_reversed_registry_files = [];

    private static array $artifact_reversed_registry_files = [];

    /**
     * Get the current registry configuration.
     * "Current" depends on SPC load mode
     */
    public static function getRegistryConfig(?string $registry_name = null): array
    {
        if ($registry_name === null && spc_mode(SPC_MODE_SOURCE)) {
            return self::$registry_configs['core'];
        }
        if ($registry_name !== null && isset(self::$registry_configs[$registry_name])) {
            return self::$registry_configs[$registry_name];
        }
        if ($registry_name === null) {
            throw new RegistryException('No registry name specified.');
        }
        throw new RegistryException("Registry '{$registry_name}' is not loaded.");
    }

    /**
     * Load a registry from file path.
     * This method handles external registries that may not be in composer autoload.
     *
     * @param string $registry_file Path to registry file (json or yaml)
     * @param bool   $auto_require  Whether to auto-require PHP files (for external plugins)
     */
    public static function loadRegistry(string $registry_file, bool $auto_require = true): void
    {
        $yaml = @file_get_contents($registry_file);
        if ($yaml === false) {
            throw new RegistryException("Failed to read registry file: {$registry_file}");
        }
        $data = match (pathinfo($registry_file, PATHINFO_EXTENSION)) {
            'json' => json_decode($yaml, true),
            'yaml', 'yml' => Yaml::parse($yaml),
            default => throw new RegistryException("Unsupported registry file format: {$registry_file}"),
        };
        if (!is_array($data)) {
            throw new RegistryException("Invalid registry format in file: {$registry_file}");
        }
        $registry_name = $data['name'] ?? null;
        if (!is_string($registry_name) || empty($registry_name)) {
            throw new RegistryException("Registry 'name' is missing or invalid in file: {$registry_file}");
        }

        // Prevent loading the same registry twice
        if (in_array($registry_name, self::$loaded_registries, true)) {
            logger()->debug("Registry '{$registry_name}' already loaded, skipping.");
            return;
        }
        self::$loaded_registries[] = $registry_name;
        self::$registry_configs[$registry_name] = $data;
        self::$registry_configs[$registry_name]['_file'] = $registry_file;

        logger()->debug("Loading registry '{$registry_name}' from file: {$registry_file}");

        self::$current_registry_name = $registry_name;

        // Load composer autoload if specified (for external registries with their own dependencies)
        if (isset($data['autoload']) && is_string($data['autoload'])) {
            $autoload_path = FileSystem::fullpath($data['autoload'], dirname($registry_file));
            if (file_exists($autoload_path)) {
                logger()->debug("Loading external autoload from: {$autoload_path}");
                require_once $autoload_path;
            } else {
                logger()->warning("Autoload file not found: {$autoload_path}");
            }
        }

        // load package configs
        if (isset($data['package']['config']) && is_array($data['package']['config'])) {
            foreach ($data['package']['config'] as $path) {
                $path = FileSystem::fullpath($path, dirname($registry_file));
                if (is_file($path)) {
                    self::$loaded_package_configs[] = PackageConfig::loadFromFile($path, $registry_name);
                } elseif (is_dir($path)) {
                    self::$loaded_package_configs = array_merge(self::$loaded_package_configs, PackageConfig::loadFromDir($path, $registry_name));
                }
            }
        }

        // load artifact configs
        if (isset($data['artifact']['config']) && is_array($data['artifact']['config'])) {
            foreach ($data['artifact']['config'] as $path) {
                $path = FileSystem::fullpath($path, dirname($registry_file));
                if (is_file($path)) {
                    self::$loaded_artifact_configs[] = ArtifactConfig::loadFromFile($path, $registry_name);
                } elseif (is_dir($path)) {
                    self::$loaded_package_configs = array_merge(self::$loaded_package_configs, ArtifactConfig::loadFromDir($path, $registry_name));
                }
            }
        }

        // load doctor items from PSR-4 directories
        if (isset($data['doctor']['psr-4']) && is_assoc_array($data['doctor']['psr-4'])) {
            foreach ($data['doctor']['psr-4'] as $namespace => $path) {
                $path = FileSystem::fullpath($path, dirname($registry_file));
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

        // load packages from PSR-4 directories
        if (isset($data['package']['psr-4']) && is_assoc_array($data['package']['psr-4'])) {
            foreach ($data['package']['psr-4'] as $namespace => $path) {
                $path = FileSystem::fullpath($path, dirname($registry_file));
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
                $path = FileSystem::fullpath($path, dirname($registry_file));
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
                $path = FileSystem::fullpath($path, dirname($registry_file));
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
        self::$current_registry_name = null;
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
     * Resolve loaded registries.
     * This method finalizes the loading process by registering default stages
     * and validating stage events.
     */
    public static function resolve(): void
    {
        // Register default stages for all PhpExtensionPackage instances
        // This must be done after all registries are loaded to ensure custom stages take precedence
        PackageLoader::registerAllDefaultStages();

        // check BeforeStage, AfterStage is valid
        PackageLoader::checkLoadedStageEvents();

        // Validate package dependencies
        self::validatePackageDependencies();
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
     * Bind a package name to its registry config file for reverse lookup.
     *
     * @internal
     */
    public static function _bindPackageConfigFile(string $package_name, string $registry_name, string $config_file): void
    {
        self::$package_reversed_registry_files[$package_name] = [
            'registry' => $registry_name,
            'config' => $config_file,
        ];
    }

    /**
     * Bind an artifact name to its registry config file for reverse lookup.
     *
     * @internal
     */
    public static function _bindArtifactConfigFile(string $artifact_name, string $registry_name, string $config_file): void
    {
        self::$artifact_reversed_registry_files[$artifact_name] = [
            'registry' => $registry_name,
            'config' => $config_file,
        ];
    }

    public static function getPackageConfigInfo(string $package_name): ?array
    {
        return self::$package_reversed_registry_files[$package_name] ?? null;
    }

    public static function getArtifactConfigInfo(string $artifact_name): ?array
    {
        return self::$artifact_reversed_registry_files[$artifact_name] ?? null;
    }

    public static function getLoadedPackageConfigs(): array
    {
        return self::$loaded_package_configs;
    }

    public static function getLoadedArtifactConfigs(): array
    {
        return self::$loaded_artifact_configs;
    }

    public static function getCurrentRegistryName(): ?string
    {
        return self::$current_registry_name;
    }

    /**
     * Validate package dependencies to ensure all referenced dependencies exist.
     * This helps catch configuration errors early in the registry loading process.
     *
     * @throws RegistryException
     */
    private static function validatePackageDependencies(): void
    {
        $all_packages = PackageConfig::getAll();
        $errors = [];

        foreach ($all_packages as $pkg_name => $pkg_config) {
            // Check depends field
            $depends = PackageConfig::get($pkg_name, 'depends', []);
            if (!is_array($depends)) {
                $errors[] = "Package '{$pkg_name}' has invalid 'depends' field (expected array, got " . gettype($depends) . ')';
                continue;
            }

            foreach ($depends as $dep) {
                if (!isset($all_packages[$dep])) {
                    $config_info = self::getPackageConfigInfo($pkg_name);
                    $location = $config_info ? " (defined in {$config_info['config']})" : '';
                    $errors[] = "Package '{$pkg_name}'{$location} depends on '{$dep}' which does not exist in any loaded registry";
                }
            }

            // Check suggests field
            $suggests = PackageConfig::get($pkg_name, 'suggests', []);
            if (!is_array($suggests)) {
                $errors[] = "Package '{$pkg_name}' has invalid 'suggests' field (expected array, got " . gettype($suggests) . ')';
                continue;
            }

            foreach ($suggests as $suggest) {
                if (!isset($all_packages[$suggest])) {
                    $config_info = self::getPackageConfigInfo($pkg_name);
                    $location = $config_info ? " (defined in {$config_info['config']})" : '';
                    $errors[] = "Package '{$pkg_name}'{$location} suggests '{$suggest}' which does not exist in any loaded registry";
                }
            }
        }

        if (!empty($errors)) {
            throw new RegistryException("Package dependency validation failed:\n  - " . implode("\n  - ", $errors));
        }
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
            $full_path = FileSystem::fullpath($file_path, $base_path);
            require_once $full_path;
            return;
        }

        // Class not found and no file path provided
        throw new RegistryException(
            "Class '{$class}' not found. For external registries, either:\n" .
            "  1. Add an 'autoload' entry pointing to your composer autoload file\n" .
            "  2. Use 'psr-4' instead of 'classes' for auto-discovery\n" .
            "  3. Provide file path in classes map: \"{$class}\": \"path/to/file.php\""
        );
    }
}
