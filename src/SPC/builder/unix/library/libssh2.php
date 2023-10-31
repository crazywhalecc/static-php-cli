<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libssh2
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $enable_zlib = $this->builder->getLib('zlib') !== null ? 'ON' : 'OFF';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                "{$this->builder->makeCmakeArgs()} " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                "-DENABLE_ZLIB_COMPRESSION={$enable_zlib} " .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libssh2.pc']);
    }
}
