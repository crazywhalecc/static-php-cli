<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libxml2 extends WindowsLibraryBase
{
    public const NAME = 'libxml2';

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
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_STATIC_LIBS=ON ' .
                "-DLIBXML2_WITH_ZLIB={$zlib} " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_ICONV=ON ' .
                '-DIconv_LIBRARY=' . BUILD_LIB_PATH . ' ' .
                '-DIconv_INCLUDE_DIR=' . BUILD_INCLUDE_PATH . ' ' .
                '-DLIBXML2_WITH_LZMA=OFF ' . // xz not supported yet
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
                '-DLIBXML2_WITH_TESTS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        copy(BUILD_LIB_PATH . '\libxml2s.lib', BUILD_LIB_PATH . '\libxml2_a.lib');
    }
}
