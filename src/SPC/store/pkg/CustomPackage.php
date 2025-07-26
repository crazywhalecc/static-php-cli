<?php

declare(strict_types=1);

namespace SPC\store\pkg;

abstract class CustomPackage
{
    abstract public function getSupportName(): array;

    abstract public function fetch(string $name, bool $force = false, ?array $config = null): void;

    abstract public function extract(string $name): void;

    abstract public static function getEnvironment(): array;

    abstract public static function isInstalled(): bool;
}
