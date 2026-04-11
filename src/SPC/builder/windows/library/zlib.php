<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class zlib extends WindowsLibraryBase
{
    public const NAME = 'zlib';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\build');

        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                '-B build ' .
                '-A x64 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DSKIP_INSTALL_FILES=ON ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
        $detect_list = [
            'zlibstatic.lib',
            'zs.lib',
            'libzs.lib',
            'libz.lib',
        ];
        foreach ($detect_list as $item) {
            if (file_exists(BUILD_LIB_PATH . '\\' . $item)) {
                FileSystem::copy(BUILD_LIB_PATH . '\\' . $item, BUILD_LIB_PATH . '\zlib_a.lib');
                FileSystem::copy(BUILD_LIB_PATH . '\\' . $item, BUILD_LIB_PATH . '\zlibstatic.lib');
                break;
            }
        }
        FileSystem::removeFileIfExists(BUILD_ROOT_PATH . '\bin\zlib.dll');
        FileSystem::removeFileIfExists(BUILD_LIB_PATH . '\zlib.lib');
        FileSystem::removeFileIfExists(BUILD_LIB_PATH . '\libz.dll');
        FileSystem::removeFileIfExists(BUILD_LIB_PATH . '\libz.lib');
        FileSystem::removeFileIfExists(BUILD_LIB_PATH . '\z.lib');
        FileSystem::removeFileIfExists(BUILD_LIB_PATH . '\z.dll');
    }
}
