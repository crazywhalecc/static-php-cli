<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class libyaml extends WindowsLibraryBase
{
    public const NAME = 'libyaml';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\build');

        // check missing files: cmake\config.h.in and .\YamlConfig.cmake.in
        if (!file_exists($this->source_dir . '\cmake\config.h.in')) {
            FileSystem::createDir($this->source_dir . '\cmake');
            copy(ROOT_DIR . '\src\globals\extra\libyaml_config.h.in', $this->source_dir . '\cmake\config.h.in');
        }
        if (!file_exists($this->source_dir . '\YamlConfig.cmake.in')) {
            copy(ROOT_DIR . '\src\globals\extra\libyaml_YamlConfig.cmake.in', $this->source_dir . '\YamlConfig.cmake.in');
        }

        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                '-B build ' .
                '-A x64 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' '
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build build --config Release --target install -j{$this->builder->concurrency}"
            );
    }
}
