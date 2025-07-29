<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

/**
 * Custom extension attribute and manager
 *
 * This class provides functionality to register and manage custom PHP extensions
 * that can be used during the build process.
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS)]
class CustomExt
{
    private static array $custom_ext_class = [];

    /**
     * Constructor for custom extension attribute
     *
     * @param string $ext_name The extension name
     */
    public function __construct(protected string $ext_name) {}

    /**
     * Load all custom extension classes
     *
     * This method scans the extension directory and registers all classes
     * that have the CustomExt attribute.
     *
     * @throws \ReflectionException
     * @throws FileSystemException
     */
    public static function loadCustomExt(): void
    {
        $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/builder/extension', 'SPC\builder\extension');
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getAttributes(CustomExt::class) as $attribute) {
                self::$custom_ext_class[$attribute->getArguments()[0]] = $class;
            }
        }
    }

    /**
     * Get the class name for a custom extension
     *
     * @param  string $ext_name The extension name
     * @return string The class name for the extension
     */
    public static function getExtClass(string $ext_name): string
    {
        return self::$custom_ext_class[$ext_name] ?? Extension::class;
    }
}
