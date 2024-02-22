<?php

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libssh2 extends WindowsLibraryBase
{
    public const NAME = 'libssh2';

    protected function build(): void
    {
        $zlib = $this->builder->getLib('zlib') ? 'ON' : 'OFF';
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
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_STATIC_LIBS=ON ' .
                '-DBUILD_TESTING=OFF ' .
                "-DENABLE_ZLIB_COMPRESSION={$zlib} " .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}