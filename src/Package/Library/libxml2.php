<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('libxml2')]
class libxml2
{
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $package): void
    {
        $cmake = UnixCMakeExecutor::create($package)
            ->optionalPackage(
                'zlib',
                '-DLIBXML2_WITH_ZLIB=ON ' .
                "-DZLIB_LIBRARY={$package->getLibDir()}/libz.a " .
                "-DZLIB_INCLUDE_DIR={$package->getIncludeDir()}",
                '-DLIBXML2_WITH_ZLIB=OFF',
            )
            ->optionalPackage('xz', ...cmake_boolean_args('LIBXML2_WITH_LZMA'))
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                '-DLIBXML2_WITH_ICU=OFF', // optional, but discouraged: https://gitlab.gnome.org/GNOME/libxml2/-/blob/master/README.md
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            );

        if (SystemTarget::getTargetOS() === 'Linux') {
            $cmake->addConfigureArgs('-DIconv_IS_BUILT_IN=OFF');
        }

        $cmake->build();

        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/pkgconfig/libxml-2.0.pc',
            '-lxml2 -liconv',
            '-lxml2'
        );
        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/pkgconfig/libxml-2.0.pc',
            '-lxml2',
            '-lxml2 -liconv'
        );
    }
}
