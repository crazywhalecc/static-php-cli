<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libevent')]
class libevent
{
    #[BeforeStage('libevent', 'packPrebuilt')]
    public function beforePack(LibraryPackage $lib): void
    {
        $cmake_file = 'lib/cmake/libevent/LibeventTargets-static.cmake';
        if (file_exists(BUILD_ROOT_PATH . '/' . $cmake_file)) {
            // get pack placeholder defines
            $placeholder = get_pack_replace();

            // replace actual paths with placeholders
            FileSystem::replaceFileRegex(
                BUILD_ROOT_PATH . '/' . $cmake_file,
                '/set\(_IMPORT_PREFIX .*\)/m',
                'set(_IMPORT_PREFIX "' . $placeholder[BUILD_ROOT_PATH] . '")'
            );

            FileSystem::replaceFileRegex(
                BUILD_ROOT_PATH . '/' . $cmake_file,
                '/INTERFACE_INCLUDE_DIRECTORIES ".*"/m',
                'INTERFACE_INCLUDE_DIRECTORIES "${_IMPORT_PREFIX}/include"'
            );

            FileSystem::replaceFileRegex(
                BUILD_ROOT_PATH . '/' . $cmake_file,
                '/INTERFACE_LINK_LIBRARIES "libevent::core;.*"/m',
                'INTERFACE_LINK_LIBRARIES "libevent::core;${_IMPORT_PREFIX}/lib/libssl.a;${_IMPORT_PREFIX}/lib/libcrypto.a"'
            );

            // add this file to postinstall for path replacement
            $lib->addPostinstallAction([
                'action' => 'replace-path',
                'files' => [$cmake_file],
            ]);
        }
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $cmake = UnixCMakeExecutor::create($lib)
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

        $lib->patchPkgconfPrefix(['libevent.pc', 'libevent_core.pc', 'libevent_extra.pc', 'libevent_openssl.pc']);

        $lib->patchPkgconfPrefix(
            ['libevent_openssl.pc'],
            PKGCONF_PATCH_CUSTOM,
            [
                '/Libs.private:.*/m',
                'Libs.private: -lssl -lcrypto',
            ]
        );
    }
}
