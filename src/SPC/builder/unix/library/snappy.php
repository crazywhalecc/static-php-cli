<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait snappy
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        FileSystem::resetDir($this->source_dir . '/cmake/build');

        shell()->cd($this->source_dir . '/cmake/build')
            ->exec(
                'cmake ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                "{$this->builder->makeCmakeArgs()} " .
                '-DSNAPPY_BUILD_TESTS=OFF ' .
                '-DSNAPPY_BUILD_BENCHMARKS=OFF ' .
                '../..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
    }
}
