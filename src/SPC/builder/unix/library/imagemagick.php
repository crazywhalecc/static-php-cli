<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait imagemagick
{
    protected function build(): void
    {
        $extra = '--without-jxl --without-xml --without-zstd --without-x --disable-openmp ';
        // libzip support
        $extra .= $this->builder->getLib('libzip') ? '--with-zip ' : '--without-zip ';
        // jpeg support
        $extra .= $this->builder->getLib('libjpeg') ? '--with-jpeg ' : '';
        // png support
        $extra .= $this->builder->getLib('libpng') ? '--with-png ' : '';
        // webp support
        $extra .= $this->builder->getLib('libwebp') ? '--with-webp ' : '';
        // zstd support
        // $extra .= $this->builder->getLib('zstd') ? '--with-zstd ' : '--without-zstd ';
        // freetype support
        $extra .= $this->builder->getLib('freetype') ? '--with-freetype ' : '--without-freetype ';

        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static --disable-shared ' .
                $extra .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $filelist = [
            'ImageMagick.pc',
            'ImageMagick-7.Q16HDRI.pc',
            'Magick++.pc',
            'Magick++-7.Q16HDRI.pc',
            'MagickCore.pc',
            'MagickCore-7.Q16HDRI.pc',
            'MagickWand.pc',
            'MagickWand-7.Q16HDRI.pc',
        ];
        $this->patchPkgconfPrefix($filelist);
        foreach ($filelist as $file) {
            FileSystem::replaceFile(
                BUILD_LIB_PATH . '/pkgconfig/' . $file,
                REPLACE_FILE_PREG,
                '#includearchdir=/include/ImageMagick-7#m',
                'includearchdir=${prefix}/include/ImageMagick-7'
            );
        }
    }
}
