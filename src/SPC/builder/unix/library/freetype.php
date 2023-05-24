<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait freetype
{
    protected function build()
    {
        $suggested = $this->builder->getLib('libpng') ? '--with-png' : '--without-png';
        $suggested .= ' ';
        $suggested .= $this->builder->getLib('bzip2') ? ('--with-bzip2=' . BUILD_ROOT_PATH) : '--without-bzip2';
        $suggested .= ' ';
        $suggested .= $this->builder->getLib('brotli') ? ('--with-brotli=' . BUILD_ROOT_PATH) : '--without-brotli';
        $suggested .= ' ';

        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static --disable-shared --without-harfbuzz --prefix= ' .
                $suggested
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['freetype2.pc']);
        FileSystem::replaceFile(
            BUILD_ROOT_PATH . '/lib/pkgconfig/freetype2.pc',
            REPLACE_FILE_STR,
            ' -L/lib ',
            ' -L' . BUILD_ROOT_PATH . '/lib '
        );

        $this->cleanLaFiles();
    }
}
