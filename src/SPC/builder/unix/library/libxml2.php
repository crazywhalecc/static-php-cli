<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

trait libxml2
{
    /**
     * @throws FileSystemException
     */
    public function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->optionalLib('zlib', "-DLIBXML2_WITH_ZLIB=ON -DZLIB_LIBRARY={$this->getLibDir()}/libz.a -DZLIB_INCLUDE_DIR={$this->getIncludeDir()}", '-DLIBXML2_WITH_ZLIB=OFF')
            ->optionalLib('icu', ...cmake_boolean_args('LIBXML2_WITH_ICU'))
            ->optionalLib('xz', ...cmake_boolean_args('LIBXML2_WITH_LZMA'))
            ->addConfigureArgs(
                '-DLIBXML2_WITH_ICONV=ON',
                '-DLIBXML2_WITH_PYTHON=OFF',
                '-DLIBXML2_WITH_PROGRAMS=OFF',
                '-DLIBXML2_WITH_TESTS=OFF',
            );

        if ($this instanceof LinuxLibraryBase) {
            $cmake->addConfigureArgs('-DIconv_IS_BUILD_IN=OFF');
        }

        $cmake->build();

        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/pkgconfig/libxml-2.0.pc',
            '-licudata -licui18n -licuuc',
            '-licui18n -licuuc -licudata'
        );
    }
}
