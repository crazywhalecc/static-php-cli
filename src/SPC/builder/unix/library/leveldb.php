<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait leveldb
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "cmake {$this->builder->makeCmakeArgs()} " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DLEVELDB_BUILD_TESTS=OFF ' .
                '-DLEVELDB_BUILD_BENCHMARKS=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
    }
}
