<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\traits\UnixLibraryTrait;
use SPC\store\Config;

abstract class MacOSLibraryBase extends LibraryBase
{
    use UnixLibraryTrait;

    protected array $headers;

    public function __construct(protected MacOSBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }

    public function getFrameworks(): array
    {
        return Config::getLib(static::NAME, 'frameworks', []);
    }
}
