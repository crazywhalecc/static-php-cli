<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class gmssl extends WindowsLibraryBase
{
    public const NAME = 'gmssl';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\builddir');

        // start build
        cmd()->cd($this->source_dir . '\builddir')
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake .. -G "NMake Makefiles" -DWIN32=ON -DBUILD_SHARED_LIBS=OFF -DCMAKE_BUILD_TYPE=Release -DCMAKE_C_FLAGS_RELEASE="/MT /O2 /Ob2 /DNDEBUG" -DCMAKE_CXX_FLAGS_RELEASE="/MT /O2 /Ob2 /DNDEBUG" -DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH),
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH
            );

        FileSystem::writeFile($this->source_dir . '\builddir\cmake_install.cmake', 'set(CMAKE_INSTALL_PREFIX "' . str_replace('\\', '/', BUILD_ROOT_PATH) . '")' . PHP_EOL . FileSystem::readFile($this->source_dir . '\builddir\cmake_install.cmake'));

        cmd()->cd($this->source_dir . '\builddir')
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('nmake'),
                'install XCFLAGS=/MT'
            );
    }
}
