<?php

declare(strict_types=1);

namespace StaticPHP\Artifact;

use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\Artifact\BinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\SourceExtract;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Util\FileSystem;

class ArtifactLoader
{
    /** @var null|array<string, Artifact> Artifact instances */
    private static ?array $artifacts = null;

    public static function initArtifactInstances(): void
    {
        if (self::$artifacts !== null) {
            return;
        }
        foreach (ArtifactConfig::getAll() as $name => $item) {
            $artifact = new Artifact($name, $item);
            self::$artifacts[$name] = $artifact;
        }
    }

    public static function getArtifactInstance(string $artifact_name): ?Artifact
    {
        self::initArtifactInstances();
        return self::$artifacts[$artifact_name] ?? null;
    }

    /**
     * Load artifact definitions from PSR-4 directory.
     *
     * @param string $dir            Directory path
     * @param string $base_namespace Base namespace for dir's PSR-4 mapping
     * @param bool   $auto_require   Whether to auto-require PHP files (for external plugins not in autoload)
     */
    public static function loadFromPsr4Dir(string $dir, string $base_namespace, bool $auto_require = false): void
    {
        self::initArtifactInstances();
        $classes = FileSystem::getClassesPsr4($dir, $base_namespace, auto_require: $auto_require);
        foreach ($classes as $class) {
            self::loadFromClass($class);
        }
    }

    public static function loadFromClass(string $class): void
    {
        $ref = new \ReflectionClass($class);

        $class_instance = $ref->newInstance();

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            self::processCustomSourceAttribute($ref, $method, $class_instance);
            self::processCustomBinaryAttribute($ref, $method, $class_instance);
            self::processSourceExtractAttribute($ref, $method, $class_instance);
            self::processBinaryExtractAttribute($ref, $method, $class_instance);
            self::processAfterSourceExtractAttribute($ref, $method, $class_instance);
            self::processAfterBinaryExtractAttribute($ref, $method, $class_instance);
        }
    }

    /**
     * Process #[CustomSource] attribute.
     */
    private static function processCustomSourceAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(CustomSource::class);
        foreach ($attributes as $attribute) {
            /** @var CustomSource $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                self::$artifacts[$artifact_name]->setCustomSourceCallback([$class_instance, $method->getName()]);
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[CustomSource] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }

    /**
     * Process #[CustomBinary] attribute.
     */
    private static function processCustomBinaryAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(CustomBinary::class);
        foreach ($attributes as $attribute) {
            /** @var CustomBinary $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                foreach ($instance->support_os as $os) {
                    self::$artifacts[$artifact_name]->setCustomBinaryCallback($os, [$class_instance, $method->getName()]);
                }
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[CustomBinary] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }

    /**
     * Process #[SourceExtract] attribute.
     * This attribute allows completely taking over the source extraction process.
     */
    private static function processSourceExtractAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(SourceExtract::class);
        foreach ($attributes as $attribute) {
            /** @var SourceExtract $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                self::$artifacts[$artifact_name]->setSourceExtractCallback([$class_instance, $method->getName()]);
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[SourceExtract] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }

    /**
     * Process #[BinaryExtract] attribute.
     * This attribute allows completely taking over the binary extraction process.
     */
    private static function processBinaryExtractAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(BinaryExtract::class);
        foreach ($attributes as $attribute) {
            /** @var BinaryExtract $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                self::$artifacts[$artifact_name]->setBinaryExtractCallback(
                    [$class_instance, $method->getName()],
                    $instance->platforms
                );
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[BinaryExtract] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }

    /**
     * Process #[AfterSourceExtract] attribute.
     * This attribute registers a hook that runs after source extraction completes.
     */
    private static function processAfterSourceExtractAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(AfterSourceExtract::class);
        foreach ($attributes as $attribute) {
            /** @var AfterSourceExtract $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                self::$artifacts[$artifact_name]->addAfterSourceExtractCallback([$class_instance, $method->getName()]);
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[AfterSourceExtract] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }

    /**
     * Process #[AfterBinaryExtract] attribute.
     * This attribute registers a hook that runs after binary extraction completes.
     */
    private static function processAfterBinaryExtractAttribute(\ReflectionClass $ref, \ReflectionMethod $method, object $class_instance): void
    {
        $attributes = $method->getAttributes(AfterBinaryExtract::class);
        foreach ($attributes as $attribute) {
            /** @var AfterBinaryExtract $instance */
            $instance = $attribute->newInstance();
            $artifact_name = $instance->artifact_name;
            if (isset(self::$artifacts[$artifact_name])) {
                self::$artifacts[$artifact_name]->addAfterBinaryExtractCallback(
                    [$class_instance, $method->getName()],
                    $instance->platforms
                );
            } else {
                throw new ValidationException("Artifact '{$artifact_name}' not found for #[AfterBinaryExtract] on '{$ref->getName()}::{$method->getName()}'");
            }
        }
    }
}
