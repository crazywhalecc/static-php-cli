<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libxslt extends WindowsLibraryBase
{
    public const NAME = 'libxslt';

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
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DLIBXSLT_WITH_PYTHON=OFF ' .
                '-DLIBXSLT_WITH_CRYPTO=OFF ' .
                '-DLIBXSLT_WITH_DEBUGGER=OFF ' .
                '-DLIBXSLT_WITH_PROGRAMS=OFF ' .
                '-DLIBXSLT_WITH_TESTS=OFF ' .
                '-DLIBXSLT_WITH_MODULES=OFF ' .
                '-DLibXml2_ROOT=' . BUILD_ROOT_PATH . ' ' .
                '-DCMAKE_PREFIX_PATH=' . BUILD_ROOT_PATH . ' ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        copy(BUILD_LIB_PATH . '\libxslts.lib', BUILD_LIB_PATH . '\libxslt.lib');
        copy(BUILD_LIB_PATH . '\libexslts.lib', BUILD_LIB_PATH . '\libexslt.lib');
    }
}
