<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\OptionalCheck;
use SPC\store\FileSystem;

/**
 * AttributeMapper is responsible for mapping extension names to their respective classes
 * using PHP attributes.
 *
 * This class scans the extension classes for the CustomExt attribute and builds a mapping
 * of extension names to their class names, which can be used to retrieve the class by name.
 * @internal it is intended for internal use within the SPC builder framework
 */
class AttributeMapper
{
    /** @param array<string, string> $extensions The mapping of extension names to their classes */
    private static array $ext_attr_map = [];

    /** @var array<string, array<string, array|callable>> $doctor_map The mapping of doctor modules */
    private static array $doctor_map = [
        'check' => [],
        'fix' => [],
    ];

    public static function init(): void
    {
        // Load CustomExt attributes from extension classes
        self::loadExtensionAttributes();

        // Load doctor check items
        self::loadDoctorAttributes();

        // TODO: 3.0, refactor library loader and vendor loader here
    }

    /**
     * Get the class name of an extension by its attributed name.
     *
     * @param  string      $name The name of the extension (attributed name)
     * @return null|string Returns the class name of the extension if it exists, otherwise null
     */
    public static function getExtensionClassByName(string $name): ?string
    {
        return self::$ext_attr_map[$name] ?? null;
    }

    /**
     * @internal
     */
    public static function getDoctorCheckMap(): array
    {
        return self::$doctor_map['check'];
    }

    /**
     * @internal
     */
    public static function getDoctorFixMap(): array
    {
        return self::$doctor_map['fix'];
    }

    private static function loadExtensionAttributes(): void
    {
        $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/builder/extension', 'SPC\builder\extension');
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getAttributes(CustomExt::class) as $attribute) {
                /** @var CustomExt $instance */
                $instance = $attribute->newInstance();
                self::$ext_attr_map[$instance->ext_name] = $class;
            }
        }
    }

    private static function loadDoctorAttributes(): void
    {
        $classes = FileSystem::getClassesPsr4(ROOT_DIR . '/src/SPC/doctor/item', 'SPC\doctor\item');
        foreach ($classes as $class) {
            $optional_passthrough = null;
            $ref = new \ReflectionClass($class);
            // #[OptionalCheck]
            $optional = $ref->getAttributes(OptionalCheck::class)[0] ?? null;
            if ($optional !== null) {
                /** @var OptionalCheck $instance */
                $instance = $optional->newInstance();
                if (is_callable($instance->check)) {
                    $optional_passthrough = $instance->check;
                }
            }
            $check_items = [];
            $fix_items = [];
            // load check items and fix items
            foreach ($ref->getMethods() as $method) {
                $optional_passthrough_single = $optional_passthrough ?? null;
                // #[OptionalCheck]
                foreach ($method->getAttributes(OptionalCheck::class) as $method_attr) {
                    $optional_check = $method_attr->newInstance();
                    if (is_callable($optional_check->check)) {
                        $optional_passthrough_single = $optional_check->check;
                    }
                }
                // #[AsCheckItem]
                foreach ($method->getAttributes(AsCheckItem::class) as $method_attr) {
                    // [{AsCheckItem object}, {OptionalCheck callable or null}]
                    $obj = $method_attr->newInstance();
                    $obj->callback = [new $class(), $method->getName()];
                    $check_items[] = [$obj, $optional_passthrough_single];
                }
                // #[AsFixItem]
                $fix_item = $method->getAttributes(AsFixItem::class)[0] ?? null;
                if ($fix_item !== null) {
                    // [{AsFixItem object}, {OptionalCheck callable or null}]
                    $obj = $fix_item->newInstance();
                    $fix_items[$obj->name] = [new $class(), $method->getName()];
                }
            }

            // add to doctor map
            self::$doctor_map['check'] = array_merge(self::$doctor_map['check'], $check_items);
            self::$doctor_map['fix'] = array_merge(self::$doctor_map['fix'], $fix_items);
        }

        // sort check items by level
        usort(self::$doctor_map['check'], fn (array $a, array $b) => $a[0]->level > $b[0]->level ? -1 : ($a[0]->level == $b[0]->level ? 0 : 1));
    }
}
