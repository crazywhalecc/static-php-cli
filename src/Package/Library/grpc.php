<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\LinuxUtil;

#[Library('grpc')]
class grpc
{
    #[PatchBeforeBuild]
    #[PatchDescription('Fix re2 pcre.h include order for compatibility')]
    public function patchBeforeBuild(LibraryPackage $lib): bool
    {
        FileSystem::replaceFileStr(
            "{$lib->getSourceDir()}/third_party/re2/util/pcre.h",
            ["#define UTIL_PCRE_H_\n#include <stdint.h>", '#define UTIL_PCRE_H_'],
            ['#define UTIL_PCRE_H_', "#define UTIL_PCRE_H_\n#include <stdint.h>"],
        );
        return true;
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function buildUnix(ToolchainInterface $toolchain, LibraryPackage $lib): void
    {
        $cmake = UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/avoid_BUILD_file_conflict")
            ->addConfigureArgs(
                "-DgRPC_INSTALL_BINDIR={$lib->getBinDir()}",
                "-DgRPC_INSTALL_LIBDIR={$lib->getLibDir()}",
                "-DgRPC_INSTALL_SHAREDIR={$lib->getBuildRootPath()}/share/grpc",
                "-DCMAKE_C_FLAGS=\"-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L{$lib->getLibDir()} -I{$lib->getIncludeDir()}\"",
                "-DCMAKE_CXX_FLAGS=\"-DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK -L{$lib->getLibDir()} -I{$lib->getIncludeDir()}\"",
                '-DgRPC_BUILD_CODEGEN=OFF',
                '-DgRPC_DOWNLOAD_ARCHIVES=OFF',
                '-DgRPC_BUILD_TESTS=OFF',
                // providers
                '-DgRPC_ZLIB_PROVIDER=package',
                '-DgRPC_CARES_PROVIDER=package',
                '-DgRPC_SSL_PROVIDER=package',
            );

        if (PHP_OS_FAMILY === 'Linux' && $toolchain->isStatic() && !LinuxUtil::isMuslDist()) {
            $cmake->addConfigureArgs(
                '-DCMAKE_EXE_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_SHARED_LINKER_FLAGS="-static-libgcc -static-libstdc++"',
                '-DCMAKE_CXX_STANDARD_LIBRARIES="-static-libgcc -static-libstdc++"',
            );
        }

        $cmake->build();

        $re2Content = file_get_contents("{$lib->getSourceDir()}/third_party/re2/re2.pc");
        $re2Content = "prefix={$lib->getBuildRootPath()}\nexec_prefix=\${prefix}\n{$re2Content}";
        file_put_contents("{$lib->getLibDir()}/pkgconfig/re2.pc", $re2Content);
        $lib->patchPkgconfPrefix(['grpc++.pc', 'grpc.pc', 'grpc++_unsecure.pc', 'grpc_unsecure.pc', 're2.pc']);
    }
}
