<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait librdkafka
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/lds-gen.py',
            "funcs.append('rd_ut_coverage_check')",
            ''
        );
        FileSystem::replaceFileStr(
            $this->source_dir . '/src/rd.h',
            '#error "IOV_MAX not defined"',
            "#define IOV_MAX 1024\n#define __GNU__"
        );
        return true;
    }

    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-Wno-int-conversion -Wno-unused-but-set-variable -Wno-unused-variable'])
            ->optionalLib(
                'zstd',
                function ($lib) {
                    putenv("STATIC_LIB_libzstd={$lib->getLibDir()}/libzstd.a");
                    return '--enable-zstd';
                },
                '--disable-zstd'
            )
            ->optionalLib(
                'curl',
                function () {
                    $pkg_libs = shell()->execWithResult('pkg-config --libs --static libcurl')[1];
                    putenv("STATIC_LIB_libcurl={$pkg_libs}");
                    return '--enable-curl';
                },
                '--disable-curl'
            )
            ->optionalLib('openssl', '--enable-ssl', '--disable-ssl')
            ->optionalLib('zlib', '--enable-zlib', '--disable-zlib')
            ->removeConfigureArgs(
                '--with-pic',
                '--enable-pic',
            )
            ->configure(
                '--disable-sasl',
                '--disable-valgrind',
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
