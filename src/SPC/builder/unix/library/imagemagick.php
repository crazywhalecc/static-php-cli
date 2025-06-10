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
        $openmp = '--enable-openmp';
        // TODO: glibc rh 10 toolset's libgomp.a was built without -fPIC so we can't use openmp without depending on libgomp.so
        if (getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10')) {
            $openmp = '--disable-openmp';
        }
        $extra = "--without-jxl --without-x {$openmp} ";
        $required_libs = '';
        $optional_libs = [
            'libzip' => 'zip',
            'libjpeg' => 'jpeg',
            'libpng' => 'png',
            'libwebp' => 'webp',
            'libxml2' => 'xml',
            'libheif' => 'heic',
            'zlib' => 'zlib',
            'xz' => 'lzma',
            'zstd' => 'zstd',
            'freetype' => 'freetype',
            'bzip2' => 'bzlib',
        ];
        foreach ($optional_libs as $lib => $option) {
            $extra .= $this->builder->getLib($lib) ? "--with-{$option} " : "--without-{$option} ";
            if ($this->builder->getLib($lib) instanceof LinuxLibraryBase) {
                $required_libs .= ' ' . $this->builder->getLib($lib)->getStaticLibFiles();
            }
        }

        $ldflags = ($this instanceof LinuxLibraryBase) && getenv('SPC_LIBC') !== 'glibc' ? ('-static -ldl') : '-ldl';

        // libxml iconv patch
        $required_libs .= $this instanceof MacOSLibraryBase ? ('-liconv') : '';
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->appendEnv(['LDFLAGS' => $ldflags, 'LIBS' => $required_libs, 'PKG_CONFIG' => '$PKG_CONFIG --static'])
            ->exec(
                './configure ' .
                '--enable-static --disable-shared --with-pic ' .
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
        $this->patchLaDependencyPrefix();
    }
}
