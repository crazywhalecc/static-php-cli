<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libsodium
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec('./configure --enable-static --disable-shared --prefix=')
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libsodium.pc'], PKGCONF_PATCH_PREFIX);
    }
}
