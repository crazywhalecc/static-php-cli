<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libwebp extends WindowsLibraryBase
{
    public const NAME = 'libwebp';

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
                '-DWEBP_LINK_STATIC=ON ' .
                '-DWEBP_BUILD_ANIM_UTILS=OFF ' .
                '-DWEBP_BUILD_CWEBP=OFF ' .
                '-DWEBP_BUILD_DWEBP=OFF ' .
                '-DWEBP_BUILD_GIF2WEBP=OFF ' .
                '-DWEBP_BUILD_IMG2WEBP=OFF ' .
                '-DWEBP_BUILD_VWEBP=OFF ' .
                '-DWEBP_BUILD_WEBPINFO=OFF ' .
                '-DWEBP_BUILD_LIBWEBPMUX=OFF ' .
                '-DWEBP_BUILD_WEBPMUX=OFF ' .
                '-DWEBP_BUILD_EXTRAS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}
