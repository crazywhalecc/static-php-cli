<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libavif extends WindowsLibraryBase
{
    public const NAME = 'libavif';

    protected function build(): void
    {
        // workaround for libavif 1.2.0 bug
        FileSystem::replaceFileStr($this->source_dir . '\src\read.c', 'avifFileType ftyp = {};', 'avifFileType ftyp = { 0 };');
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
                '-DAVIF_BUILD_APPS=OFF ' .
                '-DAVIF_BUILD_TESTS=OFF ' .
                '-DAVIF_LIBYUV=OFF ' .
                '-DAVID_ENABLE_GTEST=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}
