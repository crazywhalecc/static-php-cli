<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait librdkafka
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        $cflags = $this->builder->arch_c_flags;
        $cxxflags = $this->builder->arch_cxx_flags;
        $patched_cflags = preg_replace('/\s*-flto(=\S*)?\s*/', ' ', $cflags);
        $patched_cxxflags = preg_replace('/\s*-flto(=\S*)?\s*/', ' ', $cxxflags);

        $this->builder->arch_c_flags = $patched_cflags;
        $this->builder->arch_cxx_flags = $patched_cxxflags;

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
        UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-Wno-int-conversion -Wno-unused-but-set-variable -Wno-unused-variable'])
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

        $this->builder->arch_c_flags = $cflags;
        $this->builder->arch_cxx_flags = $cxxflags;
    }
}
