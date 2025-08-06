<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

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

    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DEVENT__LIBRARY_TYPE=STATIC',
                '-DEVENT__DISABLE_BENCHMARK=ON',
                '-DEVENT__DISABLE_THREAD_SUPPORT=ON',
                '-DEVENT__DISABLE_TESTS=ON',
                '-DEVENT__DISABLE_SAMPLES=ON',
                '-DEVENT__DISABLE_MBEDTLS=ON ',
            );
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $cmake->addConfigureArgs('-DCMAKE_POLICY_VERSION_MINIMUM=3.10');
        }
        $cmake->build();

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
        parent::install();
        FileSystem::replaceFileStr(
            BUILD_LIB_PATH . '/cmake/libevent/LibeventTargets-static.cmake',
            '{BUILD_ROOT_PATH}',
            BUILD_ROOT_PATH
        );
    }
}
