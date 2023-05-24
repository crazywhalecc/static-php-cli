<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait libavif
{
    protected function build()
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} cmake " .
                $this->builder->makeCmakeArgs() . ' ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libavif.pc']);
        $this->cleanLaFiles();
    }
}
