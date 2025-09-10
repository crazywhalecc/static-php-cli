<?php

declare(strict_types=1);

namespace SPC\store\pkg;

/**
 * Abstract base class for custom package implementations
 *
 * This class provides a framework for implementing custom package download
 * and extraction logic. Extend this class to create custom package handlers.
 */
abstract class CustomPackage
{
    /**
     * Get the list of package names supported by this implementation
     *
     * @return array Array of supported package names
     */
    abstract public function getSupportName(): array;

    /**
     * Fetch the package from its source
     *
     * @param string     $name   Package name
     * @param bool       $force  Force download even if already exists
     * @param null|array $config Optional configuration array
     */
    abstract public function fetch(string $name, bool $force = false, ?array $config = null): void;

    /**
     * Get the environment variables this package needs to be usable.
     */
    abstract public static function getEnvironment(): array;

    /**
     * Get the PATH required to use this package.
     */
    abstract public static function getPath(): ?string;

    abstract public static function isInstalled(): bool;

    /**
     * Extract the downloaded package
     *
     * @param string $name Package name
     */
    abstract public function extract(string $name): void;
}
