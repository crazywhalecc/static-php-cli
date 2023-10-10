<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\SourcePatcher;

trait libpam
{
    public function patchBeforeBuild(): bool
    {
        return SourcePatcher::patchFile('linux-pam_musl_termios.patch', $this->source_dir);
    }

    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec('./autogen.sh')
            ->exec("{$this->builder->configure_env} ./configure --enable-static --disable-shared " .
                ($this->builder->getLib('openssl') ? '-enable-openssl=' . BUILD_ROOT_PATH . ' ' : '') .
                '--disable-prelude --disable-audit --enable-db=no --disable-nis --disable-selinux ' .
                '--disable-econf --disable-nls --disable-rpath --disable-pie --disable-doc --disable-examples --prefix=')
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['pam.pc', 'pam_misc.pc', 'pamc.pc']);
    }
}
