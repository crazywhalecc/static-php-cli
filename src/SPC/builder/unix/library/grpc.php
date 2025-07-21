<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\SystemUtil;
use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait grpc
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/third_party/re2/util/pcre.h',
            ["#define UTIL_PCRE_H_\n#include <stdint.h>", '#define UTIL_PCRE_H_'],
            ['#define UTIL_PCRE_H_', "#define UTIL_PCRE_H_\n#include <stdint.h>"],
        );
        return true;
    }

    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->setBuildDir($this->source_dir . '/avoid_BUILD_file_conflict')
            ->addConfigureArgs(
                '-DgRPC_SSL_PROVIDER=package',
                '-DgRPC_INSTALL_BINDIR=' . BUILD_BIN_PATH,
                '-DgRPC_INSTALL_LIBDIR=' . BUILD_LIB_PATH,
                '-DgRPC_INSTALL_SHAREDIR=' . BUILD_ROOT_PATH . '/share/grpc',
                '-DCMAKE_C_FLAGS="-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '"',
                '-DCMAKE_CXX_FLAGS="-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '"'
            );

        if (PHP_OS_FAMILY === 'Linux' && SPCTarget::isStatic() && !SystemUtil::isMuslDist()) {
            $cmake->addConfigureArgs(
                '-DCMAKE_EXE_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_SHARED_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_CXX_STANDARD_LIBRARIES="-static-libgcc -static-libstdc++"',
            );
        }

        $cmake->build();
        copy($this->source_dir . '/third_party/re2/re2.pc', BUILD_LIB_PATH . '/pkgconfig/re2.pc');

        // shell()->cd($this->source_dir)
        //    ->exec('EXTRA_DEFINES=GRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK EMBED_OPENSSL=false CXXFLAGS="-L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '" make static -j' . $this->builder->concurrency);
        // copy($this->source_dir . '/libs/opt/libgrpc.a', BUILD_LIB_PATH . '/libgrpc.a');
        // copy($this->source_dir . '/libs/opt/libboringssl.a', BUILD_LIB_PATH . '/libboringssl.a');
        // if (!file_exists(BUILD_LIB_PATH . '/libcares.a')) {
        //    copy($this->source_dir . '/libs/opt/libcares.a', BUILD_LIB_PATH . '/libcares.a');
        // }
        // FileSystem::copyDir($this->source_dir . '/include/grpc', BUILD_INCLUDE_PATH . '/grpc');
        // FileSystem::copyDir($this->source_dir . '/include/grpc++', BUILD_INCLUDE_PATH . '/grpc++');
        // FileSystem::copyDir($this->source_dir . '/include/grpcpp', BUILD_INCLUDE_PATH . '/grpcpp');
    }
}
