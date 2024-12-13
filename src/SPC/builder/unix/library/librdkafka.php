<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait librdkafka
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        $builddir = BUILD_ROOT_PATH;

        $zstd_option = $this->builder->getLib('zstd') ? ("STATIC_LIB_libzstd={$builddir}/lib/libzstd.a ") : '';
        shell()->cd($this->source_dir)
            ->exec(
                $zstd_option .
                './configure ' .
                '--enable-static --disable-shared --disable-curl --disable-sasl --disable-valgrind --disable-zlib --disable-ssl ' .
                ($zstd_option == '' ? '--disable-zstd ' : '') .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$builddir}");
        $this->patchPkgconfPrefix(['rdkafka.pc', 'rdkafka-static.pc', 'rdkafka++.pc', 'rdkafka++-static.pc']);
        // remove dynamic libs
        shell()
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");
    }
}
