<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait ldap
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                $this->builder->configure_env . ' ' .
                'CC="musl-gcc -I' . BUILD_INCLUDE_PATH . '" ' .
                'LDFLAGS="-static -L' . BUILD_LIB_PATH . '" ' .
                ($this->builder->getLib('openssl') && $this->builder->getExt('zlib') ? 'LIBS="-lssl -lcrypto -lz" ' : '') .
                ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-slapd ' .
                '--disable-slurpd ' .
                '--without-systemd ' .
                ($this->builder->getLib('openssl') && $this->builder->getExt('zlib') ? '--with-tls=openssl ' : '') .
                ($this->builder->getLib('gmp') ? '--with-mp=gmp ' : '') .
                ($this->builder->getLib('libsodium') ? '--with-argon2=libsodium ' : '') .
                '--prefix='
            )
            ->exec('make clean')
            ->exec('make depend')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['ldap.pc', 'lber.pc']);
    }
}
