<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Executor;

use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\ToolPackage;

abstract class Executor
{
    public function __construct(protected LibraryPackage|ToolPackage $package) {}

    public static function create(LibraryPackage|ToolPackage $package): static
    {
        return new static($package);
    }
}
