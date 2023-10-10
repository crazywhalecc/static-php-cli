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
                "{$this->builder->configure_env} cmake " .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DCMAKE_INSTALL_PREFIX=' . escapeshellarg(BUILD_ROOT_PATH) . ' ' .
                '-DSNAPPY_BUILD_TESTS=OFF ' .
                '-DSNAPPY_BUILD_BENCHMARKS=OFF ' .
                '../..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        if (file_exists(BUILD_ROOT_PATH . '/lib64/libsnappy.a')) {
            shell()->exec('cp -rf ' . BUILD_ROOT_PATH . '/lib64/* ' . BUILD_ROOT_PATH . '/lib/');
        }
    }
}
