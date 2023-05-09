<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\traits\UnixLibraryTrait;
use SPC\exception\RuntimeException;

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

    /**
     * @throws RuntimeException
     */
    public function tryBuild(bool $force_build = false): int
    {
        // 传入 true，表明直接编译
        if ($force_build) {
            logger()->info('Building required library [' . static::NAME . ']');
            $this->build();
            return BUILD_STATUS_OK;
        }

        // 看看这些库是不是存在，如果不存在，则调用编译并返回结果状态
        foreach ($this->getStaticLibs() as $name) {
            if (!file_exists(BUILD_LIB_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // 头文件同理
        foreach ($this->getHeaders() as $name) {
            if (!file_exists(BUILD_INCLUDE_PATH . "/{$name}")) {
                $this->tryBuild(true);
                return BUILD_STATUS_OK;
            }
        }
        // pkg-config 做特殊处理，如果是 pkg-config 就检查有没有 pkg-config 二进制
        if (static::NAME === 'pkg-config' && !file_exists(BUILD_ROOT_PATH . '/bin/pkg-config')) {
            $this->tryBuild(true);
            return BUILD_STATUS_OK;
        }
        // 到这里说明所有的文件都存在，就跳过编译
        return BUILD_STATUS_ALREADY;
    }

    protected function makeFakePkgconfs()
    {
        $workspace = BUILD_ROOT_PATH;
        if ($workspace === '/') {
            $workspace = '';
        }
        foreach ($this->pkgconfs as $name => $content) {
            file_put_contents(BUILD_LIB_PATH . "/pkgconfig/{$name}", "prefix={$workspace}\n" . $content);
        }
    }
}
