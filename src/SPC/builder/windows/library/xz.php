<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class xz extends WindowsLibraryBase
{
    public const NAME = 'xz';

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
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );

        // copy liblzma.lib to liblzma_a.lib
        copy(BUILD_LIB_PATH . '/lzma.lib', BUILD_LIB_PATH . '/liblzma_a.lib');
        // patch lzma.h
        FileSystem::replaceFileStr(BUILD_INCLUDE_PATH . '/lzma.h', 'defined(LZMA_API_STATIC)', 'defined(_WIN32)');
    }
}
