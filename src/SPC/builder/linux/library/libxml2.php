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
        $enable_zlib = $this->builder->getLib('zlib') ? 'ON' : 'OFF';
        $enable_icu = $this->builder->getLib('icu') ? 'ON' : 'OFF';
        $enable_xz = $this->builder->getLib('xz') ? 'ON' : 'OFF';

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} " . ' cmake ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DCMAKE_INSTALL_PREFIX=' . escapeshellarg(BUILD_ROOT_PATH) . ' ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_BINDIR=' . escapeshellarg(BUILD_ROOT_PATH . '/bin') . ' ' .
                '-DLIBXML2_WITH_ICONV=ON ' .
                '-DIconv_IS_BUILT_IN=OFF ' .
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
        if (file_exists(BUILD_ROOT_PATH . '/lib64/libxml2.a')) {
            FileSystem::replaceFileStr(
                BUILD_ROOT_PATH . '/lib64/pkgconfig/libxml-2.0.pc',
                '-licudata -licui18n -licuuc',
                '-licui18n -licuuc -licudata'
            );
            shell()->exec('cp -rf ' . BUILD_ROOT_PATH . '/lib64/* ' . BUILD_ROOT_PATH . '/lib/');
        } else {
            FileSystem::replaceFileStr(
                BUILD_ROOT_PATH . '/lib/pkgconfig/libxml-2.0.pc',
                '-licudata -licui18n -licuuc',
                '-licui18n -licuuc -licudata'
            );
        }
    }
}
