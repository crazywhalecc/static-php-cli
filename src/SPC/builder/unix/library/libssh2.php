<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait libssh2
{
    protected function build()
    {
        $enable_zlib = $this->builder->getLib('zlib') !== null ? 'ON' : 'OFF';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} " . ' cmake ' .
                "{$this->builder->makeCmakeArgs()} " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                "-DENABLE_ZLIB_COMPRESSION={$enable_zlib} " .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency} --target libssh2")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libssh2.pc']);
    }
}
