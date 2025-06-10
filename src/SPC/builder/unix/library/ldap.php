<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait ldap
{
    public function patchBeforeBuild(): bool
    {
        $extra = getenv('SPC_LIBC') === 'glibc' ? '-ldl -lpthread -lm -lresolv -lutil' : '';
        FileSystem::replaceFileStr($this->source_dir . '/configure', '"-lssl -lcrypto', '"-lssl -lcrypto -lz ' . $extra);
        return true;
    }

    protected function build(): void
    {
        $alt = '';
        // openssl support
        $alt .= $this->builder->getLib('openssl') ? '--with-tls=openssl ' : '';
        // gmp support
        $alt .= $this->builder->getLib('gmp') ? '--with-mp=gmp ' : '';
        // libsodium support
        $alt .= $this->builder->getLib('libsodium') ? '--with-argon2=libsodium ' : '--enable-argon2=no ';
        f_putenv('PKG_CONFIG=' . BUILD_ROOT_PATH . '/bin/pkg-config');
        f_putenv('PKG_CONFIG_PATH=' . BUILD_LIB_PATH . '/pkgconfig');
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->appendEnv(['LDFLAGS' => "-L{$this->getLibDir()}"])
            ->exec(
                $this->builder->makeAutoconfFlags(AUTOCONF_CPPFLAGS) .
                ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--with-pic ' .
                '--disable-slapd ' .
                '--without-systemd ' .
                '--without-cyrus-sasl ' .
                $alt .
                '--prefix='
            )
            ->exec('make clean')
            // remove tests and doc to prevent compile failed with error: soelim not found
            ->exec('sed -i -e "s/SUBDIRS= include libraries clients servers tests doc/SUBDIRS= include libraries clients servers/g" Makefile')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        FileSystem::replaceFileLineContainsString(BUILD_LIB_PATH . '/pkgconfig/ldap.pc', 'Libs: -L${libdir} -lldap', 'Libs: -L${libdir} -lldap -llber');
        $this->patchPkgconfPrefix(['ldap.pc', 'lber.pc']);
        $this->patchLaDependencyPrefix();
    }
}
