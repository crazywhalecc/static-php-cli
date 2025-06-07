<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libheif
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "cmake {$this->builder->makeCmakeArgs()} " .
                '--preset=release ' .
                '-DWITH_EXAMPLES=OFF ' .
                '-DWITH_GDK_PIXBUF=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                '-DWITH_LIBSHARPYUV=ON ' . // optional: libwebp
                '-DENABLE_PLUGIN_LOADING=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['libheif.pc']);
    }
}
