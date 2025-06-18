<?php

declare(strict_types=1);

namespace SPC\store\pkg;

abstract class CustomPackage
{
    abstract public function getSupportName(): array;

    abstract public function fetch(string $name, bool $force = false, ?array $config = null): void;

    public function extract(string $name): void
    {
        throw new \RuntimeException("Extract method not implemented for package: {$name}");
    }
}
