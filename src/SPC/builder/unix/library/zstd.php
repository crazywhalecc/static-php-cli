<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait zstd
{
    protected function build()
    {
        FileSystem::resetDir($this->source_dir . '/build/cmake/build');
        shell()->cd($this->source_dir . '/build/cmake/build')
            ->exec(
                "{$this->builder->configure_env} cmake " .
                "{$this->builder->makeCmakeArgs()} " .
                '-DZSTD_BUILD_STATIC=ON ' .
                '-DZSTD_BUILD_SHARED=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libzstd.pc']);
    }
}
