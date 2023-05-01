<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait xz
{
    public function build()
    {
        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->builder->gnu_arch}-unknown-linux " .
                '--disable-scripts ' .
                '--disable-doc ' .
                '--with-libiconv ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['liblzma.pc']);
    }
}
