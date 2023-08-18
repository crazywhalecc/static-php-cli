<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class glfw extends MacOSLibraryBase
{
    public const NAME = 'glfw';

    protected function build()
    {
        // compile！
        shell()->cd(SOURCE_PATH . '/ext-glfw/vendor/glfw')
            ->exec("{$this->builder->configure_env} cmake . {$this->builder->makeCmakeArgs()} -DBUILD_SHARED_LIBS=OFF -DGLFW_BUILD_EXAMPLES=OFF -DGLFW_BUILD_TESTS=OFF")
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        // patch pkgconf
        $this->patchPkgconfPrefix(['glfw3.pc']);
    }
}
