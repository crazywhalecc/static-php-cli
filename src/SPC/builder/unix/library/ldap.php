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
                'LDFLAGS="-static"' .
                ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-slapd ' .
                '--without-systemd ' .
                ($this->builder->getLib('openssl') ? '--with-tls=openssl ' : '') .
                '--prefix='
            )
            ->exec('make clean')
            ->exec('make depend')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['ldap.pc', 'lber.pc']);
    }
}
