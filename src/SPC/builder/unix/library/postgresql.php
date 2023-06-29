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
        shell()->cd($this->source_dir)->exec('mkdir -p build  ');
        shell()->cd($this->source_dir . '/build')->exec('rm -rf ./* ');
        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')
            ->exec(
                <<<'EOF'
            sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile 
EOF
            )
            ->exec(
                <<<EOF
            {$this->builder->configure_env} \\
            ../configure  \\
            --prefix={$builddir} \\
            --enable-coverage=no \\
            --with-ssl=openssl  \\
            --with-readline \\
            --with-icu \\
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


            make -C src/bin/pg_config install
            make -C src/include install

            make -C  src/common install

            make -C  src/backend/port install
            make -C  src/port install

            make -C  src/backend/libpq install
            make -C  src/interfaces/libpq install
            
            rm -rf {$builddir}/lib/*.so.*
            rm -rf {$builddir}/lib/*.so
            rm -rf {$builddir}/lib/*.dylib
        
EOF
            );
        $this->patchPkgconfPrefix(['libpq.pc', 'libecpg.pc', 'libecpg_compat.pc']);
    }
}
