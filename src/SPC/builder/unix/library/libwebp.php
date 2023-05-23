<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libwebp
{
    protected function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec('./autogen.sh')
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                '--prefix= ' .
                '--enable-libwebpdecoder ' .
                '--enable-libwebpextras ' .
                '--disable-tiff ' .
                '--disable-gl ' .
                '--disable-sdl ' .
                '--disable-wic'
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . $destdir);
        $this->patchPkgconfPrefix(['libsharpyuv.pc', 'libwebp.pc', 'libwebpdecoder.pc', 'libwebpdemux.pc', 'libwebpmux.pc'], PKGCONF_PATCH_PREFIX);
        $this->cleanLaFiles();
    }
}
