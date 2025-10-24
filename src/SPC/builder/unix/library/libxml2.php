<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait libxml2
{
    public function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->optionalLib(
                'zlib',
                '-DLIBXML2_WITH_ZLIB=ON ' .
                "-DZLIB_LIBRARY={$this->getLibDir()}/libz.a " .
                "-DZLIB_INCLUDE_DIR={$this->getIncludeDir()}",
                '-DLIBXML2_WITH_ZLIB=OFF',
            )
            ->optionalLib('xz', ...cmake_boolean_args('LIBXML2_WITH_LZMA'))
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                '-DLIBXML2_WITH_ICU=OFF', // optional, but discouraged: https://gitlab.gnome.org/GNOME/libxml2/-/blob/master/README.md
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            );

        if ($this instanceof LinuxLibraryBase) {
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
