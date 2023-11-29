<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait postgresql
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $builddir = BUILD_ROOT_PATH;
        $envs = '';
        $packages = 'openssl zlib readline libxml-2.0 zlib';
        $optional_packages = [
            'zstd' => 'libzstd',
            'ldap' => 'ldap',
            'libxslt' => 'libxslt',
            'icu' => 'icu-i18n',
        ];

        foreach ($optional_packages as $lib => $pkg) {
            if ($this->getBuilder()->getLib($lib)) {
                $packages .= ' ' . $pkg;

                $output = shell()->execWithResult("pkg-config --cflags --libs --static {$pkg}")[1][0];
                if (!empty($output[1][0])) {
                    logger()->info($output[1][0]);
                }
            }
        }

        $output = shell()->execWithResult("pkg-config --cflags-only-I --static {$packages}");
        if (!empty($output[1][0])) {
            $cppflags = $output[1][0];
            $envs .= " CPPFLAGS=\"{$cppflags}\"";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-L --static {$packages}");
        if (!empty($output[1][0])) {
            $ldflags = $output[1][0];
            $envs .= $this instanceof MacOSLibraryBase ? " LDFLAGS=\"{$ldflags}\" " : " LDFLAGS=\"{$ldflags} -static\" ";
        }
        $output = shell()->execWithResult("pkg-config --libs-only-l --static {$packages}");
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            $libcpp = '';
            if ($this->builder->getLib('icu')) {
                $libcpp = $this instanceof LinuxLibraryBase ? ' -lstdc++' : ' -lc++';
            }
            $envs .= " LIBS=\"{$libs}{$libcpp}\" ";
        }

        FileSystem::resetDir($this->source_dir . '/build');

        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')
            ->exec('sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile')
            ->exec('sed -i.backup "278 s/^/# /"  ../src/Makefile.shlib')
            ->exec('sed -i.backup "402 s/^/# /"  ../src/Makefile.shlib');

        // configure
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$envs} ../configure " .
                "--prefix={$builddir} " .
                '--disable-thread-safety ' .
                '--enable-coverage=no ' .
                '--with-ssl=openssl ' .
                '--with-readline ' .
                '--with-libxml ' .
                ($this->builder->getLib('icu') ? '--with-icu ' : '--without-icu ') .
                ($this->builder->getLib('ldap') ? '--with-ldap ' : '--without-ldap ') .
                ($this->builder->getLib('libxslt') ? '--with-libxslt ' : '--without-libxslt ') .
                ($this->builder->getLib('zstd') ? '--with-zstd ' : '--without-zstd ') .
                '--without-lz4 ' .
                '--without-perl ' .
                '--without-python ' .
                '--without-pam ' .
                '--without-bonjour ' .
                '--without-tcl '
            );

        // build
        shell()->cd($this->source_dir . '/build')
            ->exec($envs . ' make -C src/bin/pg_config install')
            ->exec($envs . ' make -C src/include install')
            ->exec($envs . ' make -C src/common install')
            ->exec($envs . ' make -C src/port install')
            ->exec($envs . ' make -C src/interfaces/libpq install');

        // remove dynamic libs
        shell()->cd($this->source_dir . '/build')
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");
    }
}
