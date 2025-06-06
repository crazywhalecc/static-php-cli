<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class ngtcp2 extends WindowsLibraryBase
{
    public const NAME = 'ngtcp2';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\build');

        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                '-B build ' .
                '-A x64 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DENABLE_SHARED_LIB=OFF ' .
                '-DENABLE_STATIC_LIB=ON ' .
                '-DBUILD_STATIC_LIBS=ON ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DENABLE_STATIC_CRT=ON ' .
                '-DENABLE_LIB_ONLY=ON ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}
