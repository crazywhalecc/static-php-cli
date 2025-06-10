<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait librdkafka
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib(
                'zstd',
                function ($lib) {
                    putenv("STATIC_LIB_libzstd={$lib->getLibDir()}/libzstd.a");
                    return '';
                },
                '--disable-zstd'
            )
            ->removeConfigureArgs(
                '--with-pic',
                '--enable-pic',
            )
            ->configure(
                '--disable-curl',
                '--disable-sasl',
                '--disable-valgrind',
                '--disable-zlib',
                '--disable-ssl',
            )
            ->make();

        $this->patchPkgconfPrefix(['rdkafka.pc', 'rdkafka-static.pc', 'rdkafka++.pc', 'rdkafka++-static.pc']);
        // remove dynamic libs
        shell()
            ->exec("rm -rf {$this->getLibDir()}/*.so.*")
            ->exec("rm -rf {$this->getLibDir()}/*.so")
            ->exec("rm -rf {$this->getLibDir()}/*.dylib");
    }
}
