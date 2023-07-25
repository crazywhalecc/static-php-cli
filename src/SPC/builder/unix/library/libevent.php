<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait libevent
{
    protected function build()
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} cmake " .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DEVENT__LIBRARY_TYPE=STATIC ' .
                '-DEVENT__DISABLE_BENCHMARK=ON ' .
                '-DEVENT__DISABLE_THREAD_SUPPORT=ON ' .
                '-DEVENT__DISABLE_MBEDTLS=ON ' .
                '-DEVENT__DISABLE_TESTS=ON ' .
                '-DEVENT__DISABLE_SAMPLES=ON ' .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');
        // patch pkgconfig
    }
}
