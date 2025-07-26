<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait unixodbc
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

        UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-debug',
                '--disable-dependency-tracking',
                "--with-libiconv-prefix={$this->getBuildRootPath()}",
                '--with-included-ltdl',
                '--enable-gui=no',
            )
            ->make();
        $this->patchPkgconfPrefix(['odbc.pc', 'odbccr.pc', 'odbcinst.pc']);
        $this->patchLaDependencyPrefix();

        $this->builder->arch_c_flags = $cflags;
        $this->builder->arch_cxx_flags = $cxxflags;
    }
}
