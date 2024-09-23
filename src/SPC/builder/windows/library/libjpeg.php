<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libjpeg extends WindowsLibraryBase
{
    public const NAME = 'libjpeg';

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
                '-DENABLE_SHARED=OFF ' .
                '-DENABLE_STATIC=ON ' .
                '-DBUILD_TESTING=OFF ' .
                '-DWITH_JAVA=OFF ' .
                '-DWITH_CRT_DLL=OFF ' .
                "-DENABLE_ZLIB_COMPRESSION={$zlib} " .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        copy(BUILD_LIB_PATH . '\jpeg-static.lib', BUILD_LIB_PATH . '\libjpeg_a.lib');
    }
}
