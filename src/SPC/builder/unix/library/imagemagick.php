<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait imagemagick
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $ac = UnixAutoconfExecutor::create($this)
            ->optionalLib('libzip', ...ac_with_args('zip'))
            ->optionalLib('libjpeg', ...ac_with_args('jpeg'))
            ->optionalLib('libpng', ...ac_with_args('png'))
            ->optionalLib('libwebp', ...ac_with_args('webp'))
            ->optionalLib('libxml2', ...ac_with_args('xml'))
            ->optionalLib('libheif', ...ac_with_args('heic'))
            ->optionalLib('zlib', ...ac_with_args('zlib'))
            ->optionalLib('xz', ...ac_with_args('lzma'))
            ->optionalLib('zstd', ...ac_with_args('zstd'))
            ->optionalLib('freetype', ...ac_with_args('freetype'))
            ->optionalLib('bzip2', ...ac_with_args('bzlib'))
            ->addConfigureArgs(
                // TODO: glibc rh 10 toolset's libgomp.a was built without -fPIC so we can't use openmp without depending on libgomp.so
                getenv('SPC_LIBC') === 'glibc' && str_contains(getenv('CC'), 'devtoolset-10') ? '--disable-openmp' : '--enable-openmp',
                '--without-jxl',
                '--without-x',
            );

        // special: linux musl needs `-static`
        $ldflags = ($this instanceof LinuxLibraryBase) && getenv('SPC_LIBC') !== 'glibc' ? ('-static -ldl') : '-ldl';

        // special: macOS needs -iconv
        $libs = $this instanceof MacOSLibraryBase ? '-liconv' : '';

        $ac->appendEnv([
            'LDFLAGS' => $ldflags,
            'LIBS' => $libs,
            'PKG_CONFIG' => '$PKG_CONFIG --static',
        ]);

        $ac->configure()->make();

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
