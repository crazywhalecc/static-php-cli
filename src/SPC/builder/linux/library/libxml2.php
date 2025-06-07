<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class libxml2 extends LinuxLibraryBase
{
    public const NAME = 'libxml2';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        $enable_zlib = $this->builder->getLib('zlib') ? ('ON -DZLIB_LIBRARY=' . BUILD_LIB_PATH . '/libz.a -DZLIB_INCLUDE_DIR=' . BUILD_INCLUDE_PATH) : 'OFF';
        $enable_icu = $this->builder->getLib('icu') ? 'ON' : 'OFF';
        $enable_xz = $this->builder->getLib('xz') ? 'ON' : 'OFF';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags(),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv(
                "cmake {$this->builder->makeCmakeArgs()} " .
                '-DIconv_IS_BUILT_IN=OFF ' .
                '-DLIBXML2_WITH_ICONV=ON ' .
                "-DLIBXML2_WITH_ZLIB={$enable_zlib} " .
                "-DLIBXML2_WITH_ICU={$enable_icu} " .
                "-DLIBXML2_WITH_LZMA={$enable_xz} " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
                '-DLIBXML2_WITH_TESTS=OFF ' .
                '..'
            )
            ->execWithEnv("cmake --build . -j {$this->builder->concurrency}")
            ->execWithEnv('make install');

        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/pkgconfig/libxml-2.0.pc',
            '-licudata -licui18n -licuuc',
            '-licui18n -licuuc -licudata'
        );
    }
}
