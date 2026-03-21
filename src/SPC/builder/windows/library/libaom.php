<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libaom extends WindowsLibraryBase
{
    public const NAME = 'libaom';

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
                '-DAOM_TARGET_CPU=generic ' .
                '-DENABLE_DOCS=OFF ' .
                '-DENABLE_EXAMPLES=OFF ' .
                '-DENABLE_TESTDATA=OFF ' .
                '-DENABLE_TESTS=OFF ' .
                '-DENABLE_TOOLS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}
