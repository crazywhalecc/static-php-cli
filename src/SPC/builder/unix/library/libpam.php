<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libpam
{
    protected function build(): void
    {
        $root = BUILD_ROOT_PATH;
        shell()->cd($this->source_dir)
            ->exec("{$this->builder->configure_env} ./configure --enable-static --disable-shared " .
                ($this->builder->getLib('openssl') ? '-enable-openssl=' . BUILD_ROOT_PATH . ' ' : '') .
                '--disable-prelude --disable-audit --enable-db=no --disable-nis --disable-selinux ' .
                "--disable-econf --disable-nls --disable-rpath --disable-pie --disable-doc --prefix={$root}")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
    }
}
