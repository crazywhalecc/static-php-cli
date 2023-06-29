<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

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

        $env = $this->builder->configure_env;
        $packages = 'openssl zlib icu-uc icu-io icu-i18n readline libxml-2.0 libzstd';
        $output = shell()->execWithResult($env . ' pkg-config      --libs-only-l   --static  ' . $packages);
        $libs = $output[1][0];
        $env .= " CPPFLAGS=\"-I{$builddir}/include/\" ";
        $env .= " LDFLAGS=\"-L{$builddir}/lib/\" ";
        $env .= " LIBS=\"{$libs}\" ";

        FileSystem::resetDir($this->source_dir . '/build');
        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')->exec(
            <<<'EOF'
            sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile 
EOF
        );

        shell()->cd($this->source_dir . '/build')
            ->exec(
                <<<EOF
            {$env} \\
            ../configure  \\
            --prefix={$builddir} \\
            --disable-thread-safety \\
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
EOF
            );

        shell()->cd($this->source_dir . '/build')->exec(
            <<<'EOF'
            make -C src/bin/pg_config install
            make -C src/include install

            make -C  src/common install

            make -C  src/backend/port install
            make -C  src/port install

            make -C  src/backend/libpq install
            make -C  src/interfaces/libpq install
     
EOF
        );

        shell()->cd($this->source_dir . '/build')->exec(
            <<<EOF
            rm -rf {$builddir}/lib/*.so.*
            rm -rf {$builddir}/lib/*.so
            rm -rf {$builddir}/lib/*.dylib
EOF
        );
    }
}
