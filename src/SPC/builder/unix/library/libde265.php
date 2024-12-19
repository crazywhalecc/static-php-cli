<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libde265
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
                'cmake ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DENABLE_SDL=OFF ' . // Disable SDL, currently not supported
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['libde265.pc']);
    }
}
