<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;

#[Library('imagemagick')]
class imagemagick
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, ToolchainInterface $toolchain): void
    {
        $ldflags = $original_ldflags = getenv('SPC_DEFAULT_LD_FLAGS');
        if (str_contains($ldflags, '-Wl,--as-needed')) {
            $ldflags = str_replace('-Wl,--as-needed', '', $ldflags);
            f_putenv("SPC_DEFAULT_LD_FLAGS={$ldflags}");
        }

        $ac = UnixAutoconfExecutor::create($lib)
            ->optionalPackage('libzip', ...ac_with_args('zip'))
            ->optionalPackage('libjpeg', ...ac_with_args('jpeg'))
            ->optionalPackage('libpng', ...ac_with_args('png'))
            ->optionalPackage('libwebp', ...ac_with_args('webp'))
            ->optionalPackage('libxml2', ...ac_with_args('xml'))
            ->optionalPackage('libheif', ...ac_with_args('heic'))
            ->optionalPackage('zlib', ...ac_with_args('zlib'))
            ->optionalPackage('xz', ...ac_with_args('lzma'))
            ->optionalPackage('zstd', ...ac_with_args('zstd'))
            ->optionalPackage('freetype', ...ac_with_args('freetype'))
            ->optionalPackage('bzip2', ...ac_with_args('bzlib'))
            ->optionalPackage('libjxl', ...ac_with_args('jxl'))
            ->optionalPackage('jbig', ...ac_with_args('jbig'))
            ->addConfigureArgs(
                '--disable-openmp',
                '--without-x',
            );

        // special: linux-static target needs `-static`
        $ldflags = $toolchain->isStatic() ? '-static -ldl' : '-ldl';

        // special: macOS needs -iconv
        $libs = SystemTarget::getTargetOS() === 'Darwin' ? '-liconv' : '';

        $ac->appendEnv([
            'LDFLAGS' => $ldflags,
            'LIBS' => $libs,
            'PKG_CONFIG' => '$PKG_CONFIG --static',
        ]);

        $ac->configure()->make();

        f_putenv("SPC_DEFAULT_LD_FLAGS={$original_ldflags}");

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
        $lib->patchPkgconfPrefix($filelist);
        foreach ($filelist as $file) {
            FileSystem::replaceFileRegex(
                "{$lib->getLibDir()}/pkgconfig/{$file}",
                '#includearchdir=/include/ImageMagick-7#m',
                'includearchdir=${prefix}/include/ImageMagick-7'
            );
        }
        $lib->patchLaDependencyPrefix();
    }
}
