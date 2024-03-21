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
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static --disable-shared --disable-curl --disable-sasl --disable-valgrind ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['rdkafka.pc', 'rdkafka-static.pc', 'rdkafka++.pc', 'rdkafka++-static.pc']);
        // remove dynamic libs
        shell()
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");
    }
}
