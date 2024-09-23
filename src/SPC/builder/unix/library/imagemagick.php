<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait imagemagick
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        // TODO: imagemagick build with bzip2 failed with bugs, we need to fix it in the future
        $extra = '--without-jxl --without-x --enable-openmp --without-bzlib ';
        $required_libs = '';
        $optional_libs = [
            'libzip' => 'zip',
            'libjpeg' => 'jpeg',
            'libpng' => 'png',
            'libwebp' => 'webp',
            'libxml2' => 'xml',
            'zlib' => 'zlib',
            'xz' => 'lzma',
            'zstd' => 'zstd',
            'freetype' => 'freetype',
        ];
        foreach ($optional_libs as $lib => $option) {
            $extra .= $this->builder->getLib($lib) ? "--with-{$option} " : "--without-{$option} ";
            if ($this->builder->getLib($lib) instanceof LinuxLibraryBase) {
                $required_libs .= ' ' . $this->builder->getLib($lib)->getStaticLibFiles();
            }
        }

        $ldflags = $this instanceof LinuxLibraryBase ? ('-static') : '';

        // libxml iconv patch
        $required_libs .= $this instanceof MacOSLibraryBase ? ('-liconv') : '';
        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags() ?: $ldflags,
                'LIBS' => $this->getLibExtraLibs() ?: $required_libs,
                'PKG_CONFIG' => '$PKG_CONFIG --static',
            ])
            ->execWithEnv(
                './configure ' .
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
            FileSystem::replaceFileRegex(
                BUILD_LIB_PATH . '/pkgconfig/' . $file,
                '#includearchdir=/include/ImageMagick-7#m',
                'includearchdir=${prefix}/include/ImageMagick-7'
            );
        }
    }
}
