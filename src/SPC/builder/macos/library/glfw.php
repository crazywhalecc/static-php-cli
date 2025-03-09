<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class glfw extends MacOSLibraryBase
{
    public const NAME = 'glfw';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        // compile！
        shell()->cd(SOURCE_PATH . '/ext-glfw/vendor/glfw')
            ->exec("cmake . {$this->builder->makeCmakeArgs()} -DBUILD_SHARED_LIBS=OFF -DGLFW_BUILD_EXAMPLES=OFF -DGLFW_BUILD_TESTS=OFF")
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
        // patch pkgconf
        $this->patchPkgconfPrefix(['glfw3.pc']);
    }
}
