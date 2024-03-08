<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class libxml2 extends MacOSLibraryBase
{
    public const NAME = 'libxml2';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $enable_zlib = $this->builder->getLib('zlib') ? ('ON -DZLIB_LIBRARY=' . BUILD_LIB_PATH . '/libz.a -DZLIB_INCLUDE_DIR=' . BUILD_INCLUDE_PATH) : 'OFF';
        $enable_icu = $this->builder->getLib('icu') ? 'ON' : 'OFF';
        $enable_xz = $this->builder->getLib('xz') ? 'ON' : 'OFF';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                'cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                '-DCMAKE_INSTALL_LIBDIR=' . BUILD_LIB_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DLIBXML2_WITH_ICONV=ON ' .
                "-DLIBXML2_WITH_ZLIB={$enable_zlib} " .
                "-DLIBXML2_WITH_ICU={$enable_icu} " .
                "-DLIBXML2_WITH_LZMA={$enable_xz} " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
                '-DLIBXML2_WITH_TESTS=OFF ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
