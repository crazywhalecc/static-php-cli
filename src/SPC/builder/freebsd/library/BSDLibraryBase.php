<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

use SPC\builder\BuilderBase;
use SPC\builder\freebsd\BSDBuilder;
use SPC\builder\LibraryBase;
use SPC\builder\traits\UnixLibraryTrait;

abstract class BSDLibraryBase extends LibraryBase
{
    use UnixLibraryTrait;

    protected array $headers;

    public function __construct(protected BSDBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }
}
