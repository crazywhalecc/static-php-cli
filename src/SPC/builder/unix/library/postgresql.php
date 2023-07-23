<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

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
    protected function build()
    {
        $builddir = BUILD_ROOT_PATH;
        $env = $this->builder->configure_env;
        $envs = $env;
        $packages = 'openssl zlib  readline libxml-2.0'; // icu-uc icu-io icu-i18n libzstd

        $pkgconfig_executable = $builddir . '/bin/pkg-config';
        $output = shell()->execWithResult($env . " {$pkgconfig_executable} --cflags-only-I --static " . $packages);
        if (!empty($output[1][0])) {
            $cppflags = $output[1][0];
            $envs .= " CPPFLAGS=\"{$cppflags}\"";
        }
        $output = shell()->execWithResult($env . " {$pkgconfig_executable} --libs-only-L --static " . $packages);
        if (!empty($output[1][0])) {
            $ldflags = $output[1][0];
            $envs .= $this instanceof MacOSLibraryBase ? " LDFLAGS=\"{$ldflags}\" " : " LDFLAGS=\"{$ldflags} -static\" ";
        }
        $output = shell()->execWithResult($env . " {$pkgconfig_executable} --libs-only-l --static " . $packages);
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            $envs .= " LIBS=\"{$libs}\" ";
        }

        FileSystem::resetDir($this->source_dir . '/build');

        # 有静态链接配置  参考文件： src/interfaces/libpq/Makefile
        shell()->cd($this->source_dir . '/build')
            ->exec('sed -i.backup "s/invokes exit\'; exit 1;/invokes exit\';/"  ../src/interfaces/libpq/Makefile')
            ->exec(' sed -i.backup "293 s/^/#$/"  ../src/Makefile.shlib')
            ->exec('sed -i.backup "441 s/^/#$/"  ../src/Makefile.shlib');

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
                '--without-ldap ' .
                '--without-libxslt ' .
                '--without-lz4 ' .
                '--without-zstd ' .
                '--without-perl ' .
                '--without-python ' .
                '--without-pam ' .
                '--without-ldap ' .
                '--without-bonjour ' .
                '--without-tcl '
            );

        // build
        shell()->cd($this->source_dir . '/build')
            ->exec($envs . ' make -C src/bin/pg_config install')
            ->exec($envs . ' make -C src/include install')
            ->exec($envs . ' make -C src/common install')
            ->exec($envs . ' make -C src/backend/port install')
            ->exec($envs . ' make -C src/port install')
            ->exec($envs . ' make -C src/backend/libpq install')
            ->exec($envs . ' make -C src/interfaces/libpq install');

        // remove dynamic libs
        shell()->cd($this->source_dir . '/build')
            ->exec("rm -rf {$builddir}/lib/*.so.*")
            ->exec("rm -rf {$builddir}/lib/*.so")
            ->exec("rm -rf {$builddir}/lib/*.dylib");
    }
}
