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
        $envs = $env;
        $packages = 'openssl zlib  readline libxml-2.0 '; // icu-uc icu-io icu-i18n libzstd

        $output = shell()->execWithResult($env . ' pkg-config      --cflags-only-I   --static  ' . $packages);
        if (!empty($output[1][0])) {
            $cppflags = $output[1][0];
            $envs .= " CPPFLAGS=\"{$cppflags}\"";
        }
        $output = shell()->execWithResult($env . ' pkg-config      --libs-only-L   --static  ' . $packages);
        if (!empty($output[1][0])) {
            $ldflags = $output[1][0];
            $envs .= " LDFLAGS=\"{$ldflags} -static\" ";
        }
        $output = shell()->execWithResult($env . ' pkg-config      --libs-only-l   --static  ' . $packages);
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            $envs .= " LIBS=\"{$libs}\" ";
        }

        FileSystem::resetDir($this->source_dir . '/build');

        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')->exec(
            <<<'EOF'
            sed -i.backup "s/invokes exit'; exit 1;/invokes exit';/"  ../src/interfaces/libpq/Makefile 
EOF
        );

        shell()->cd($this->source_dir . '/build')->exec(
            <<<'EOF'
            sed -i.backup "293 s/^/#$/"  ../src/Makefile.shlib
EOF
        );
        shell()->cd($this->source_dir . '/build')->exec(
            <<<'EOF'
            sed -i.backup "441 s/^/#$/"  ../src/Makefile.shlib
EOF
        );

        shell()->cd($this->source_dir . '/build')
            ->exec(
                <<<EOF
            {$envs} \\
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
            --without-zstd \\
            --without-perl \\
            --without-python \\
            --without-pam \\
            --without-ldap \\
            --without-bonjour \\
            --without-tcl
EOF
            );
        // 方便调试，
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C src/bin/pg_config install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C src/include install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C  src/common install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C  src/backend/port install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C  src/port install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C  src/backend/libpq install');
        shell()->cd($this->source_dir . '/build')->exec($envs . ' make -C  src/interfaces/libpq install');

        shell()->cd($this->source_dir . '/build')->exec(
            <<<EOF
            rm -rf {$builddir}/lib/*.so.*
            rm -rf {$builddir}/lib/*.so
            rm -rf {$builddir}/lib/*.dylib
EOF
        );
    }
}
