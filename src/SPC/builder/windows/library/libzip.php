<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libzip extends WindowsLibraryBase
{
    public const NAME = 'libzip';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\build');

        $openssl = $this->builder->getLib('openssl') ? 'ON' : 'OFF';
        $zstd = $this->builder->getLib('zstd') ? 'ON' : 'OFF';

        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                '-B build ' .
                '-A x64 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DENABLE_BZIP2=ON ' .
                '-DENABLE_LZMA=ON ' .
                "-DENABLE_ZSTD={$zstd} " .
                "-DENABLE_OPENSSL={$openssl} " .
                '-DBUILD_TOOLS=OFF ' .
                '-DBUILD_DOC=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_REGRESS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        copy(BUILD_LIB_PATH . '\zip.lib', BUILD_LIB_PATH . '\libzip_a.lib');
    }
}
