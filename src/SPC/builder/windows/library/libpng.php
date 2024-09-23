<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libpng extends WindowsLibraryBase
{
    public const NAME = 'libpng';

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
                '-DSKIP_INSTALL_PROGRAM=ON ' .
                '-DSKIP_INSTALL_FILES=ON ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DPNG_STATIC=ON ' .
                '-DPNG_SHARED=OFF ' .
                '-DPNG_TESTS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        copy(BUILD_LIB_PATH . '\libpng16_static.lib', BUILD_LIB_PATH . '\libpng_a.lib');
    }
}
