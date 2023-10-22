<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\traits\UnixLibraryTrait;

abstract class LinuxLibraryBase extends LibraryBase
{
    use UnixLibraryTrait;

    protected array $static_libs = [];

    protected array $headers;

    protected array $pkgconfs;

    /**
     * 依赖的名字及是否可选，例如：curl => true，代表依赖 curl 但可选
     */
    protected array $dep_names;

    public function __construct(protected LinuxBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }
}
