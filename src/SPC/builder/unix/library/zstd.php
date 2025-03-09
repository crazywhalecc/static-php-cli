<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait zstd
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        FileSystem::resetDir($this->source_dir . '/build/cmake/build');
        shell()->cd($this->source_dir . '/build/cmake/build')
            ->exec(
                'cmake ' .
                "{$this->builder->makeCmakeArgs()} " .
                '-DZSTD_BUILD_STATIC=ON ' .
                '-DZSTD_BUILD_SHARED=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['libzstd.pc']);
    }
}
