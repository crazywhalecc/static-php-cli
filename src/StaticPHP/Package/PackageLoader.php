<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Info;
use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\Package\ResolveBuild;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Util\FileSystem;

class PackageLoader
{
    /** @var array<string, Package> */
    private static ?array $packages = null;

    private static array $before_stages = [];

    private static array $after_stage = [];

    private static array $patch_before_builds = [];

    /** @var array<string, true> Track loaded classes to prevent duplicates */
    private static array $loaded_classes = [];

    public static function initPackageInstances(): void
    {
        if (self::$packages !== null) {
            return;
        }
        // init packages instance from config
        foreach (PackageConfig::getAll() as $name => $item) {
            $pkg = match ($item['type']) {
                'target', 'virtual-target' => new TargetPackage($name, $item['type']),
                'library' => new LibraryPackage($name, $item['type']),
                'php-extension' => new PhpExtensionPackage($name, $item['type']),
                default => null,
            };
            if ($pkg !== null) {
                self::$packages[$name] = $pkg;
            } else {
                throw new WrongUsageException("Package [{$name}] has unknown type [{$item['type']}]");
            }
        }
    }

    /**
     * Load package definitions from PSR-4 directory.
     *
     * @param string $dir            Directory path
     * @param string $base_namespace Base namespace for dir's PSR-4 mapping
     * @param bool   $auto_require   Whether to auto-require PHP files (for external plugins not in autoload)
     */
    public static function loadFromPsr4Dir(string $dir, string $base_namespace, bool $auto_require = false): void
    {
        self::initPackageInstances();
        $classes = FileSystem::getClassesPsr4($dir, $base_namespace, auto_require: $auto_require);
        foreach ($classes as $class) {
            self::loadFromClass($class);
        }
    }

    public static function hasPackage(string $name): bool
    {
        return isset(self::$packages[$name]);
    }

    /**
     * Get a Package instance by its name.
     *
     * @param  string  $name The name of the package
     * @return Package Returns the Package instance if found, otherwise null
     */
    public static function getPackage(string $name): Package
    {
        if (!isset(self::$packages[$name])) {
            throw new WrongUsageException("Package [{$name}] not found.");
        }
        return self::$packages[$name];
    }

    public static function getTargetPackage(string $name): TargetPackage
    {
        $pkg = self::getPackage($name);
        if ($pkg instanceof TargetPackage) {
            return $pkg;
        }
        throw new WrongUsageException("Package [{$name}] is not a TargetPackage.");
    }

    public static function getLibraryPackage(string $name): LibraryPackage
    {
        $pkg = self::getPackage($name);
        if ($pkg instanceof LibraryPackage) {
            return $pkg;
        }
        throw new WrongUsageException("Package [{$name}] is not a LibraryPackage.");
    }

    /**
     * Get all loaded Package instances.
     */
    public static function getPackages(array|string|null $type_filter = null): iterable
    {
        foreach (self::$packages as $name => $package) {
            if ($type_filter === null) {
                yield $name => $package;
            } elseif ($package->getType() === $type_filter) {
                yield $name => $package;
            } elseif (is_array($type_filter) && in_array($package->getType(), $type_filter, true)) {
                yield $name => $package;
            }
        }
    }

    /**
     * Init package instance from defined classes and attributes.
     *
     * @internal
     */
    public static function loadFromClass(mixed $class): void
    {
        $refClass = new \ReflectionClass($class);
        $class_name = $refClass->getName();

        // Skip if already loaded to prevent duplicate registrations
        if (isset(self::$loaded_classes[$class_name])) {
            return;
        }
        self::$loaded_classes[$class_name] = true;

        $attributes = $refClass->getAttributes();
        foreach ($attributes as $attribute) {
            $pkg = null;

            $attribute_instance = $attribute->newInstance();
            if ($attribute_instance instanceof Target === false &&
                $attribute_instance instanceof Library === false &&
                $attribute_instance instanceof Extension === false) {
                // not a package attribute
                continue;
            }
            $package_type = PackageConfig::get($attribute_instance->name, 'type');
            if ($package_type === null) {
                throw new WrongUsageException("Package [{$attribute_instance->name}] not defined in config, please check your config files.");
            }

            // if class has parent class and matches the attribute instance, use custom class
            if ($refClass->getParentClass() !== false) {
                if (is_a($class_name, Package::class, true)) {
                    self::$packages[$attribute_instance->name] = new $class_name($attribute_instance->name, $package_type);
                    $instance_class = self::$packages[$attribute_instance->name];
                }
            }

            if (!isset($instance_class)) {
                $instance_class = $refClass->newInstance();
            }

            $pkg = self::$packages[$attribute_instance->name];

            // validate package type matches
            $pkg_type_attr = match ($attribute->getName()) {
                Target::class => ['target', 'virtual-target'],
                Library::class => ['library'],
                Extension::class => ['php-extension'],
                default => null,
            };
            if (!in_array($package_type, $pkg_type_attr, true)) {
                throw new ValidationException("Package [{$attribute_instance->name}] type mismatch: config type is [{$package_type}], but attribute type is [" . implode('|', $pkg_type_attr) . '].');
            }
            if ($pkg !== null && !PackageConfig::isPackageExists($pkg->getName())) {
                throw new ValidationException("Package [{$pkg->getName()}] config not found for class {$class}");
            }

            // init method attributes
            $methods = $refClass->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                $method_attributes = $method->getAttributes();
                foreach ($method_attributes as $method_attribute) {
                    $method_instance = $method_attribute->newInstance();
                    match ($method_attribute->getName()) {
                        // #[BuildFor(PHP_OS_FAMILY)]
                        BuildFor::class => self::addBuildFunction($pkg, $method_instance, [$instance_class, $method->getName()]),
                        // #[CustomPhpConfigureArg(PHP_OS_FAMILY)]
                        CustomPhpConfigureArg::class => self::bindCustomPhpConfigureArg($pkg, $method_attribute->newInstance(), [$instance_class, $method->getName()]),
                        // #[Stage('stage_name')]
                        Stage::class => $pkg->addStage($method_attribute->newInstance()->name, [$instance_class, $method->getName()]),
                        // #[InitPackage] (run now with package context)
                        InitPackage::class => ApplicationContext::invoke([$instance_class, $method->getName()], [
                            Package::class => $pkg,
                            $pkg::class => $pkg,
                        ]),
                        // #[InitBuild]
                        ResolveBuild::class => $pkg instanceof TargetPackage ? $pkg->setResolveBuildCallback([$instance_class, $method->getName()]) : null,
                        // #[Info]
                        Info::class => $pkg->setInfoCallback([$instance_class, $method->getName()]),
                        // #[Validate]
                        Validate::class => $pkg->setValidateCallback([$instance_class, $method->getName()]),
                        // #[PatchBeforeBuild]
                        PatchBeforeBuild::class => $pkg->setPatchBeforeBuildCallback([$instance_class, $method->getName()]),
                        default => null,
                    };
                }
            }
            // register package
            self::$packages[$pkg->getName()] = $pkg;
        }

        // parse non-package available attributes
        foreach ($refClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $method_attributes = $method->getAttributes();
            foreach ($method_attributes as $method_attribute) {
                $method_instance = $method_attribute->newInstance();
                match ($method_attribute->getName()) {
                    // #[BeforeStage('package_name', 'stage')] and #[AfterStage('package_name', 'stage')]
                    BeforeStage::class => self::$before_stages[$method_instance->package_name][$method_instance->stage][] = [[$instance_class, $method->getName()], $method_instance->only_when_package_resolved],
                    AfterStage::class => self::$after_stage[$method_instance->package_name][$method_instance->stage][] = [[$instance_class, $method->getName()], $method_instance->only_when_package_resolved],
                    // #[PatchBeforeBuild()
                    default => null,
                };
            }
        }
    }

    public static function getBeforeStageCallbacks(string $package_name, string $stage): iterable
    {
        // match condition
        $installer = ApplicationContext::get(PackageInstaller::class);
        $stages = self::$before_stages[$package_name][$stage] ?? [];
        foreach ($stages as [$callback, $only_when_package_resolved]) {
            if ($only_when_package_resolved !== null && !$installer->isPackageResolved($only_when_package_resolved)) {
                continue;
            }
            yield $callback;
        }
    }

    public static function getAfterStageCallbacks(string $package_name, string $stage): array
    {
        // match condition
        $installer = ApplicationContext::get(PackageInstaller::class);
        $stages = self::$after_stage[$package_name][$stage] ?? [];
        $result = [];
        foreach ($stages as [$callback, $only_when_package_resolved]) {
            if ($only_when_package_resolved !== null && !$installer->isPackageResolved($only_when_package_resolved)) {
                continue;
            }
            $result[] = $callback;
        }
        return $result;
    }

    public static function getPatchBeforeBuildCallbacks(string $package_name): array
    {
        return self::$patch_before_builds[$package_name] ?? [];
    }

    /**
     * Bind a custom PHP configure argument callback to a php-extension package.
     */
    private static function bindCustomPhpConfigureArg(Package $pkg, object $attr, callable $fn): void
    {
        if (!$pkg instanceof PhpExtensionPackage) {
            throw new ValidationException("Class [{$pkg->getName()}] must implement PhpExtensionPackage for CustomPhpConfigureArg attribute.");
        }
        $pkg->addCustomPhpConfigureArgCallback($attr->os, $fn);
    }

    private static function addBuildFunction(Package $pkg, object $attr, callable $fn): void
    {
        $pkg->addBuildFunction($attr->os, $fn);
    }
}
