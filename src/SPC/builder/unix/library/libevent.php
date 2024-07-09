<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libevent
{
    public function beforePack(): void
    {
        if (file_exists(BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake')) {
            FileSystem::replaceFileRegex(
                BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake',
                '/set\(_IMPORT_PREFIX .*\)/m',
                'set(_IMPORT_PREFIX "{BUILD_ROOT_PATH}")'
            );

            FileSystem::replaceFileRegex(
                BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake',
                '/INTERFACE_INCLUDE_DIRECTORIES ".*"/m',
                'INTERFACE_INCLUDE_DIRECTORIES "${_IMPORT_PREFIX}/include"'
            );

            FileSystem::replaceFileRegex(
                BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake',
                '/INTERFACE_LINK_LIBRARIES "libevent::core;.*"/m',
                'INTERFACE_LINK_LIBRARIES "libevent::core;${_IMPORT_PREFIX}/lib/libssl.a;${_IMPORT_PREFIX}/lib/libcrypto.a"'
            );
        }
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                'cmake ' .
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
            ->execWithEnv("cmake --build . -j {$this->builder->concurrency}")
            ->exec('make install');

        $this->patchPkgconfPrefix(['libevent.pc', 'libevent_core.pc', 'libevent_extra.pc', 'libevent_openssl.pc']);

        $this->patchPkgconfPrefix(
            ['libevent_openssl.pc'],
            PKGCONF_PATCH_CUSTOM,
            [
                '/Libs.private:.*/m',
                'Libs.private: -lssl -lcrypto',
            ]
        );
    }

    protected function install(): void
    {
        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake',
            '{BUILD_ROOT_PATH}',
            BUILD_ROOT_PATH
        );
    }
}
