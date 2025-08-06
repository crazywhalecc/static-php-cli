<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\windows\WindowsBuilder;

abstract class WindowsLibraryBase extends LibraryBase
{
    public function __construct(protected WindowsBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }
}
