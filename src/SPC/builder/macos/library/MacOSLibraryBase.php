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

    protected array $static_libs;

    protected array $headers;

    /**
     * 依赖的名字及是否可选，例如：curl => true，代表依赖 curl 但可选
     */
    protected array $dep_names;

    public function __construct(protected MacOSBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }

    /**
     * 获取当前 lib 库依赖的 macOS framework
     */
    public function getFrameworks(): array
    {
        return Config::getLib(static::NAME, 'frameworks', []);
    }
}
