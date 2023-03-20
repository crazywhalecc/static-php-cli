<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\linux\SystemUtil;
use SPC\builder\traits\UnixLibraryTrait;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\Patcher;

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

    /**
     * @throws RuntimeException
     */
    private function _make(bool $forceBuild = false, bool $fresh = false)
    {
        if ($forceBuild || php_uname('m') !== $this->builder->arch) {
            $this->build();
            return;
        }
        $static_lib_patches = SystemUtil::findStaticLibs($this->static_libs);
        $header_patches = SystemUtil::findHeaders($this->headers);
        if (!$static_lib_patches || !$header_patches) {
            $this->build();
        } else {
            if ($this->builder->libc === 'musl_wrapper') {
                logger()->warning('libc type may not match, this may cause strange symbol missing');
            }
            $this->copyExist($static_lib_patches, $header_patches);
        }
        $this->fixPkgConfigs();
    }

    private function fixPkgConfigs()
    {
        foreach ($this->pkgconfs as $name => $_) {
            Patcher::patchLinuxPkgConfig(BUILD_LIB_PATH . "/pkgconfig/{$name}");
        }
    }

    /**
     * @throws RuntimeException
     */
    private function copyExist(array $static_lib_patches, array $header_patches): void
    {
        if (!$static_lib_patches || !$header_patches) {
            throw new RuntimeException('??? staticLibPathes or headerPathes is null');
        }
        logger()->info('using system ' . static::NAME);
        foreach ($static_lib_patches as [$path, $staticLib]) {
            @f_mkdir(BUILD_LIB_PATH . '/' . dirname($staticLib), recursive: true);
            logger()->info("copy {$path}/{$staticLib} to " . BUILD_LIB_PATH . "/{$staticLib}");
            copy("{$path}/{$staticLib}", BUILD_LIB_PATH . '/' . $staticLib);
        }
        foreach ($header_patches as [$path, $header]) {
            @f_mkdir(BUILD_INCLUDE_PATH . '/' . dirname($header), recursive: true);
            logger()->info("copy {$path}/{$header} to " . BUILD_INCLUDE_PATH . "/{$header}");
            if (is_dir("{$path}/{$header}")) {
                FileSystem::copyDir("{$path}/{$header}", BUILD_INCLUDE_PATH . "/{$header}");
            } else {
                copy("{$path}/{$header}", BUILD_INCLUDE_PATH . "/{$header}");
            }
        }
        $this->makeFakePkgconfs();
    }
}
