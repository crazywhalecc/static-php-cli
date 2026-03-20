<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('libtiff')]
class libtiff
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $libcpp = SystemTarget::getTargetOS() === 'Linux' ? '-lstdc++' : '-lc++';
        FileSystem::replaceFileStr("{$lib->getSourceDir()}/configure", '-lwebp', '-lwebp -lsharpyuv');
        FileSystem::replaceFileStr("{$lib->getSourceDir()}/configure", '-l"$lerc_lib_name"', "-l\"\$lerc_lib_name\" {$libcpp}");
        UnixAutoconfExecutor::create($lib)
            ->optionalPackage('lerc', '--enable-lerc', '--disable-lerc')
            ->optionalPackage('zstd', '--enable-zstd', '--disable-zstd')
            ->optionalPackage('libwebp', '--enable-webp', '--disable-webp')
            ->optionalPackage('xz', '--enable-lzma', '--disable-lzma')
            ->optionalPackage('jbig', '--enable-jbig', '--disable-jbig')
            ->configure(
                // zlib deps
                '--enable-zlib',
                "--with-zlib-include-dir={$lib->getIncludeDir()}",
                "--with-zlib-lib-dir={$lib->getLibDir()}",
                // libjpeg deps
                '--enable-jpeg',
                "--with-jpeg-include-dir={$lib->getIncludeDir()}",
                "--with-jpeg-lib-dir={$lib->getLibDir()}",
                '--disable-old-jpeg',
                '--disable-jpeg12',
                '--disable-libdeflate',
                '--disable-tools',
                '--disable-contrib',
                '--disable-cxx',
                '--without-x',
            )
            ->make();
        $lib->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
