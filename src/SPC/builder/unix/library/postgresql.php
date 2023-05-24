<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait postgresql
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build()
    {
        [$libdir, , $destdir] = SEPARATED_PATH;
        $builddir = BUILD_ROOT_PATH;
        shell()->cd($this->source_dir)
            ->exec('sed -i.backup "s/invokes exit\\\'; exit 1;/invokes exit\\\';/"  src/interfaces/libpq/Makefile')
            ->exec(
                <<<EOF
            {$this->builder->configure_env} \\
            ./configure  \\
            --prefix={$builddir} \\
            --enable-coverage=no \\
            --with-ssl=openssl  \\
            --with-readline \\
            --without-icu \\
            --without-ldap \\
            --with-libxml  \\
            --without-libxslt \\
            --without-lz4 \\
            --with-zstd \\
            --without-perl \\
            --without-python \\
            --without-pam \\
            --without-ldap \\
            --without-bonjour \\
            --without-tcl

            make -C src/bin install
            make -C src/include install
            make -C src/interfaces install 

            make -C  src/common install 
            make -C  src/port install 
            rm -rf {$builddir}/lib/*.so.*
            rm -rf {$builddir}/lib/*.so
            rm -rf {$builddir}/lib/*.dylib
        
EOF
            );
        $this->patchPkgconfPrefix(['libpq.pc', 'libecpg.pc', 'libecpg_compat.pc']);
    }
}
