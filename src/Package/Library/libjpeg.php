<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libjpeg')]
class libjpeg
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DENABLE_STATIC=ON',
                '-DENABLE_SHARED=OFF',
            )
            ->build();
        // patch pkgconfig
        $lib->patchPkgconfPrefix(['libjpeg.pc', 'libturbojpeg.pc']);
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DENABLE_SHARED=OFF',
                '-DENABLE_STATIC=ON',
                '-DBUILD_TESTING=OFF',
                '-DWITH_JAVA=OFF',
                '-DWITH_CRT_DLL=OFF',
            )
            ->optionalPackage('zlib', '-DENABLE_ZLIB_COMPRESSION=ON', '-DENABLE_ZLIB_COMPRESSION=OFF')
            ->build();
        FileSystem::copy("{$lib->getLibDir()}\\jpeg-static.lib", "{$lib->getLibDir()}\\libjpeg_a.lib");
    }
}
