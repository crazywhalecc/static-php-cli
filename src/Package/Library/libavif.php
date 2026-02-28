<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;

#[Library('libavif')]
class libavif
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->optionalPackage('libaom', '-DAVIF_CODEC_AOM=SYSTEM', '-DAVIF_CODEC_AOM=OFF')
            ->optionalPackage('libsharpyuv', '-DAVIF_LIBSHARPYUV=SYSTEM', '-DAVIF_LIBSHARPYUV=OFF')
            ->optionalPackage('libjpeg', '-DAVIF_JPEG=SYSTEM', '-DAVIF_JPEG=OFF')
            ->optionalPackage('libxml2', '-DAVIF_LIBXML2=SYSTEM', '-DAVIF_LIBXML2=OFF')
            ->optionalPackage('libpng', '-DAVIF_LIBPNG=SYSTEM', '-DAVIF_LIBPNG=OFF')
            ->addConfigureArgs('-DAVIF_LIBYUV=OFF')
            ->build();
        // patch pkgconfig
        $lib->patchPkgconfPrefix(['libavif.pc']);
    }
}
