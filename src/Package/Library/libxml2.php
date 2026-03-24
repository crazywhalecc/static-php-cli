<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libxml2')]
class libxml2
{
    #[BuildFor('Windows')]
    public function buildForWindows(LibraryPackage $lib, PackageInstaller $installer): void
    {
        $iconv_win = $installer->getLibraryPackage('libiconv-win');
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                "-DIconv_LIBRARY={$iconv_win->getLibDir()}",
                "-DIconv_INCLUDE_DIR={$iconv_win->getIncludeDir()}",
                '-DLIBXML2_WITH_ZLIB=ON',
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_LZMA=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            )
            ->build();
        FileSystem::copy("{$lib->getLibDir()}\\libxml2s.lib", "{$lib->getLibDir()}\\libxml2_a.lib");
    }

    #[BuildFor('Linux')]
    public function buildForLinux(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                '-DIconv_IS_BUILT_IN=OFF',
                '-DLIBXML2_WITH_ZLIB=ON',
                "-DZLIB_LIBRARY={$lib->getLibDir()}/libz.a",
                "-DZLIB_INCLUDE_DIR={$lib->getIncludeDir()}",
                '-DLIBXML2_WITH_LZMA=ON',
                '-DLIBXML2_WITH_ICU=OFF', // optional, but discouraged: https://gitlab.gnome.org/GNOME/libxml2/-/blob/master/README.md
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            )
            ->build();

        $this->patchPkgConfig($lib);
    }

    #[BuildFor('Darwin')]
    public function buildForDarwin(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->optionalPackage(
                'zlib',
                '-DLIBXML2_WITH_ZLIB=ON ' .
                "-DZLIB_LIBRARY={$lib->getLibDir()}/libz.a " .
                "-DZLIB_INCLUDE_DIR={$lib->getIncludeDir()}",
                '-DLIBXML2_WITH_ZLIB=OFF',
            )
            ->optionalPackage('xz', ...cmake_boolean_args('LIBXML2_WITH_LZMA'))
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                '-DLIBXML2_WITH_ICU=OFF',
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            )
            ->build();

        $this->patchPkgConfig($lib);
    }

    private function patchPkgConfig(LibraryPackage $lib): void
    {
        $pcFile = "{$lib->getLibDir()}/pkgconfig/libxml-2.0.pc";

        // Remove -liconv from original
        FileSystem::replaceFileStr($pcFile, '-lxml2 -liconv', '-lxml2');

        // Add -liconv after -lxml2
        FileSystem::replaceFileStr($pcFile, '-lxml2', '-lxml2 -liconv');
    }
}
