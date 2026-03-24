<?php

declare(strict_types=1);

namespace StaticPHP\Registry;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\Util\FileSystem;

class DoctorLoader
{
    /**
     * @var array<int, array{0: CheckItem, 1: callable}> $doctor_items Loaded doctor check item instances
     */
    private static array $doctor_items = [];

    /**
     * @var array<string, callable> $fix_items loaded doctor fix item instances
     */
    private static array $fix_items = [];

    /**
     * Load doctor check items from PSR-4 directory.
     *
     * @param string $dir            Directory path
     * @param string $base_namespace Base namespace for dir's PSR-4 mapping
     * @param bool   $auto_require   Whether to auto-require PHP files (for external plugins not in autoload)
     */
    public static function loadFromPsr4Dir(string $dir, string $base_namespace, bool $auto_require = false): void
    {
        $classes = FileSystem::getClassesPsr4($dir, $base_namespace, auto_require: $auto_require);
        foreach ($classes as $class) {
            self::loadFromClass($class, false);
        }

        // sort check items by level
        usort(self::$doctor_items, function ($a, $b) {
            return $a[0]->level > $b[0]->level ? -1 : ($a[0]->level == $b[0]->level ? 0 : 1);
        });
    }

    /**
     * Load doctor check items from a class.
     *
     * @param string $class Class name to load doctor check items from
     * @param bool   $sort  Whether to re-sort Doctor items (default: true)
     */
    public static function loadFromClass(string $class, bool $sort = true): void
    {
        // passthough to all the functions if #[OptionalCheck] is set on class level
        $optional_passthrough = null;
        $reflection = new \ReflectionClass($class);
        $class_instance = $reflection->newInstance();
        // parse #[OptionalCheck]
        $optional = $reflection->getAttributes(OptionalCheck::class)[0] ?? null;
        if ($optional !== null) {
            /** @var OptionalCheck $instance */
            $instance = $optional->newInstance();
            if (is_callable($instance->check)) {
                $optional_passthrough = $instance->check;
            }
        }

        $doctor_items = [];
        $fix_item_map = [];

        // finx check items and fix items from methods in class
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // passthrough for this method if #[OptionalCheck] is set on method level
            $optional = $optional_passthrough ?? null;
            foreach ($method->getAttributes(OptionalCheck::class) as $method_attr) {
                $optional_check = $method_attr->newInstance();
                if (is_callable($optional_check->check)) {
                    $optional = $optional_check->check;
                }
            }

            // parse #[CheckItem]
            foreach ($method->getAttributes(CheckItem::class) as $attr) {
                /** @var CheckItem $instance */
                $instance = $attr->newInstance();
                $instance->callback = [$class_instance, $method->getName()];
                // put CheckItem instance and optional check callback (or null) to $doctor_items
                $doctor_items[] = [$instance, $optional];
            }

            // parse #[FixItem]
            $fix_item = $method->getAttributes(FixItem::class)[0] ?? null;
            if ($fix_item !== null) {
                $instance = $fix_item->newInstance();
                $fix_item_map[$instance->name] = [$class_instance, $method->getName()];
            }
        }

        // add to array
        self::$doctor_items = array_merge(self::$doctor_items, $doctor_items);
        self::$fix_items = array_merge(self::$fix_items, $fix_item_map);

        if ($sort) {
            // sort check items by level
            usort(self::$doctor_items, function ($a, $b) {
                return $a[0]->level > $b[0]->level ? -1 : ($a[0]->level == $b[0]->level ? 0 : 1);
            });
        }
    }

    /**
     * Returns loaded doctor check items.
     *
     * @return array<int, array{0: CheckItem, 1: callable}>
     */
    public static function getDoctorItems(): array
    {
        return self::$doctor_items;
    }

    public static function getFixItem(string $name): ?callable
    {
        return self::$fix_items[$name] ?? null;
    }
}
