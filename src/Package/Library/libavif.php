<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('libavif')]
class libavif
{
    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        // workaround for libavif 1.2.0 bug: MSVC does not support empty initializer list
        if (SystemTarget::getTargetOS() === 'Windows') {
            FileSystem::replaceFileStr($lib->getSourceDir() . '/src/read.c', 'avifFileType ftyp = {};', 'avifFileType ftyp = { 0 };');
        }
    }

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

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DAVIF_BUILD_APPS=OFF',
                '-DAVIF_BUILD_TESTS=OFF',
                '-DAVIF_LIBYUV=OFF',
                '-DAVIF_ENABLE_GTEST=OFF',
            )
            ->build();
    }
}
