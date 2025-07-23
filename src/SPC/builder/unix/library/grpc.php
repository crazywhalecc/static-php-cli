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
            ->setBuildDir("{$this->source_dir}/avoid_BUILD_file_conflict")
            ->addConfigureArgs(
                '-DgRPC_INSTALL_BINDIR=' . BUILD_BIN_PATH,
                '-DgRPC_INSTALL_LIBDIR=' . BUILD_LIB_PATH,
                '-DgRPC_INSTALL_SHAREDIR=' . BUILD_ROOT_PATH . '/share/grpc',
                '-DCMAKE_C_FLAGS="-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '"',
                '-DCMAKE_CXX_FLAGS="-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '"',
                '-DgRPC_BUILD_CODEGEN=OFF',
                '-DgRPC_DOWNLOAD_ARCHIVES=OFF',
                '-DgRPC_BUILD_TESTS=OFF',
                // providers
                '-DgRPC_ZLIB_PROVIDER=package',
                '-DgRPC_CARES_PROVIDER=package',
                '-DgRPC_SSL_PROVIDER=package',
            );

        if (PHP_OS_FAMILY === 'Linux' && SPCTarget::isStatic() && !SystemUtil::isMuslDist()) {
            $cmake->addConfigureArgs(
                '-DCMAKE_EXE_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_SHARED_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_CXX_STANDARD_LIBRARIES="-static-libgcc -static-libstdc++"',
            );
        }

        $cmake->build();

        $re2Content = file_get_contents($this->source_dir . '/third_party/re2/re2.pc');
        $re2Content = 'prefix=' . BUILD_ROOT_PATH . "\nexec_prefix=\${prefix}\n" . $re2Content;
        file_put_contents(BUILD_LIB_PATH . '/pkgconfig/re2.pc', $re2Content);
        $this->patchPkgconfPrefix(['grpc++.pc', 'grpc.pc', 'grpc++_unsecure.pc', 'grpc_unsecure.pc', 're2.pc']);
    }
}
