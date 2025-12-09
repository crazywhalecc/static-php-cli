<?php

declare(strict_types=1);

namespace StaticPHP\Registry;

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
use StaticPHP\Exception\RegistryException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;

class PackageLoader
{
    /** @var array<string, Package> */
    private static ?array $packages = null;

    private static array $before_stages = [];

    private static array $after_stages = [];

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
                throw new RegistryException("Package [{$name}] has unknown type [{$item['type']}]");
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
                throw new RegistryException("Package [{$attribute_instance->name}] not defined in config, please check your config files.");
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
                throw new RegistryException("Package [{$attribute_instance->name}] type mismatch: config type is [{$package_type}], but attribute type is [" . implode('|', $pkg_type_attr) . '].');
            }
            if ($pkg !== null && !PackageConfig::isPackageExists($pkg->getName())) {
                throw new RegistryException("Package [{$pkg->getName()}] config not found for class {$class}");
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
                        Stage::class => self::addStage($method, $pkg, $instance_class, $method_instance),
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

        if (!isset($instance_class)) {
            $instance_class = $refClass->newInstance();
        }

        // parse non-package available attributes
        foreach ($refClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $method_attributes = $method->getAttributes();
            foreach ($method_attributes as $method_attribute) {
                $method_instance = $method_attribute->newInstance();
                match ($method_attribute->getName()) {
                    // #[BeforeStage('package_name', 'stage')] and #[AfterStage('package_name', 'stage')]
                    BeforeStage::class => self::addBeforeStage($method, $pkg ?? null, $instance_class, $method_instance),
                    AfterStage::class => self::addAfterStage($method, $pkg ?? null, $instance_class, $method_instance),

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
        $stages = self::$after_stages[$package_name][$stage] ?? [];
        $result = [];
        foreach ($stages as [$callback, $only_when_package_resolved]) {
            if ($only_when_package_resolved !== null && !$installer->isPackageResolved($only_when_package_resolved)) {
                continue;
            }
            $result[] = $callback;
        }
        return $result;
    }

    /**
     * Register default stages for all PhpExtensionPackage instances.
     * Should be called after all registries have been loaded.
     */
    public static function registerAllDefaultStages(): void
    {
        foreach (self::$packages as $pkg) {
            if ($pkg instanceof PhpExtensionPackage) {
                $pkg->registerDefaultStages();
            }
        }
    }

    /**
     * Check loaded stage events for consistency.
     */
    public static function checkLoadedStageEvents(): void
    {
        foreach (['BeforeStage' => self::$before_stages, 'AfterStage' => self::$after_stages] as $event_name => $ev_all) {
            foreach ($ev_all as $package_name => $stages) {
                // check package exists
                if (!self::hasPackage($package_name)) {
                    throw new RegistryException(
                        "{$event_name} event registered for unknown package [{$package_name}]."
                    );
                }
                $pkg = self::getPackage($package_name);
                foreach ($stages as $stage_name => $before_events) {
                    foreach ($before_events as [$event_callable, $only_when_package_resolved]) {
                        // check only_when_package_resolved package exists
                        if ($only_when_package_resolved !== null && !self::hasPackage($only_when_package_resolved)) {
                            throw new RegistryException("{$event_name} event in package [{$package_name}] for stage [{$stage_name}] has unknown only_when_package_resolved package [{$only_when_package_resolved}].");
                        }
                        // check callable is valid
                        if (!is_callable($event_callable)) {
                            throw new RegistryException(
                                "{$event_name} event in package [{$package_name}] for stage [{$stage_name}] has invalid callable.",
                            );
                        }
                    }
                    // check stage exists
                    if (!$pkg->hasStage($stage_name)) {
                        throw new RegistryException("Package stage [{$stage_name}] is not registered in package [{$package_name}].");
                    }
                }
            }
        }
    }

    /**
     * Bind a custom PHP configure argument callback to a php-extension package.
     */
    private static function bindCustomPhpConfigureArg(Package $pkg, object $attr, callable $fn): void
    {
        if (!$pkg instanceof PhpExtensionPackage) {
            throw new RegistryException("Class [{$pkg->getName()}] must implement PhpExtensionPackage for CustomPhpConfigureArg attribute.");
        }
        $pkg->addCustomPhpConfigureArgCallback($attr->os, $fn);
    }

    private static function addBuildFunction(Package $pkg, object $attr, callable $fn): void
    {
        $pkg->addBuildFunction($attr->os, $fn);
    }

    private static function addStage(\ReflectionMethod $method, Package $pkg, object $instance_class, object $method_instance): void
    {
        $name = $method_instance->function;
        if ($name === null) {
            $name = $method->getName();
        }
        $pkg->addStage($name, [$instance_class, $method->getName()]);
    }

    private static function addBeforeStage(\ReflectionMethod $method, ?Package $pkg, mixed $instance_class, object $method_instance): void
    {
        /** @var BeforeStage $method_instance */
        $stage = $method_instance->stage;
        $stage = match (true) {
            is_string($stage) => $stage,
            is_array($stage) && count($stage) === 2 => $stage[1],
            default => throw new RegistryException('Invalid stage definition in BeforeStage attribute.'),
        };
        if ($method_instance->package_name === '' && $pkg === null) {
            throw new RegistryException('Package name must not be empty when no package context is available for BeforeStage attribute.');
        }
        $package_name = $method_instance->package_name === '' ? $pkg->getName() : $method_instance->package_name;
        self::$before_stages[$package_name][$stage][] = [[$instance_class, $method->getName()], $method_instance->only_when_package_resolved];
    }

    private static function addAfterStage(\ReflectionMethod $method, ?Package $pkg, mixed $instance_class, object $method_instance): void
    {
        $stage = $method_instance->stage;
        $stage = match (true) {
            is_string($stage) => $stage,
            is_array($stage) && count($stage) === 2 => $stage[1],
            default => throw new RegistryException('Invalid stage definition in AfterStage attribute.'),
        };
        if ($method_instance->package_name === '' && $pkg === null) {
            throw new RegistryException('Package name must not be empty when no package context is available for AfterStage attribute.');
        }
        $package_name = $method_instance->package_name === '' ? $pkg->getName() : $method_instance->package_name;
        self::$after_stages[$package_name][$stage][] = [[$instance_class, $method->getName()], $method_instance->only_when_package_resolved];
    }
}
