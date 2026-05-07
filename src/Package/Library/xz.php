<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('xz')]
class xz
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure(
                '--disable-scripts',
                '--disable-doc',
                '--with-libiconv',
                '--bindir=/tmp/xz', // xz binary will corrupt `tar` command, that's really strange.
            )
            ->make();
        $lib->patchPkgconfPrefix(['liblzma.pc']);
        $lib->patchLaDependencyPrefix();
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)->build();
        // copy lzma.lib to liblzma_a.lib
        FileSystem::copy("{$lib->getLibDir()}\\lzma.lib", "{$lib->getLibDir()}\\liblzma_a.lib");
        // patch lzma.h: make static API always available on Windows
        FileSystem::replaceFileStr("{$lib->getIncludeDir()}\\lzma.h", 'defined(LZMA_API_STATIC)', 'defined(_WIN32)');
    }
}
