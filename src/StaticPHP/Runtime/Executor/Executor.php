<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Executor;

use StaticPHP\Package\LibraryPackage;

abstract class Executor
{
    public function __construct(protected LibraryPackage $package) {}

    public static function create(LibraryPackage $package): static
    {
        return new static($package);
    }
}
