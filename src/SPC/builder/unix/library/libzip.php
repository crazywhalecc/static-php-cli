<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libzip
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $extra = '';
        // lib:bzip2
        $extra .= $this->builder->getLib('bzip2') ? '-DENABLE_BZIP2=ON ' : '-DENABLE_BZIP2=OFF ';
        // lib:xz
        $extra .= $this->builder->getLib('xz') ? '-DENABLE_LZMA=ON ' : '-DENABLE_LZMA=OFF ';
        // lib:zstd (disabled due to imagemagick link issue
        $extra .= /* $this->builder->getLib('zstd') ? '-DENABLE_ZSTD=ON ' : */ '-DENABLE_ZSTD=OFF ';
        // lib:openssl
        $extra .= $this->builder->getLib('openssl') ? '-DENABLE_OPENSSL=ON ' : '-DENABLE_OPENSSL=OFF ';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DENABLE_GNUTLS=OFF ' .
                '-DENABLE_MBEDTLS=OFF ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_DOC=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_REGRESS=OFF ' .
                '-DBUILD_TOOLS=OFF ' .
                $extra .
                '..'
            )
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
        $this->patchPkgconfPrefix(['libzip.pc'], PKGCONF_PATCH_PREFIX);
    }
}
