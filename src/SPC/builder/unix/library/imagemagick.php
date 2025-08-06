<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait imagemagick
{
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
            ->optionalLib('libjxl', ...ac_with_args('jxl'))
            ->optionalLib('jbig', ...ac_with_args('jbig'))
            ->addConfigureArgs(
                '--disable-openmp',
                '--without-x',
            );

        // special: linux-static target needs `-static`
        $ldflags = SPCTarget::isStatic() ? ('-static -ldl') : '-ldl';

        // special: macOS needs -iconv
        $libs = SPCTarget::getTargetOS() === 'Darwin' ? '-liconv' : '';

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
