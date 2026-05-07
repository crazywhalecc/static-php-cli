<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libpng')]
class libpng
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $args = [
            '--enable-hardware-optimizations',
            "--with-zlib-prefix={$lib->getBuildRootPath()}",
        ];

        // Enable architecture-specific optimizations
        match (getenv('SPC_ARCH')) {
            'x86_64' => $args[] = '--enable-intel-sse',
            'aarch64' => $args[] = '--enable-arm-neon',
            default => null,
        };

        UnixAutoconfExecutor::create($lib)
            ->exec('chmod +x ./configure')
            ->exec('chmod +x ./install-sh')
            ->appendEnv(['LDFLAGS' => "-L{$lib->getLibDir()}"])
            ->addConfigureArgs(...$args)
            ->configure()
            ->make(
                'libpng16.la',
                'install-libLTLIBRARIES install-data-am',
                after_env_vars: ['DEFAULT_INCLUDES' => "-I{$lib->getSourceDir()} -I{$lib->getIncludeDir()}"]
            );

        // patch pkgconfig
        $lib->patchPkgconfPrefix(['libpng16.pc']);
        $lib->patchLaDependencyPrefix();
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DSKIP_INSTALL_PROGRAM=ON',
                '-DSKIP_INSTALL_FILES=ON',
                '-DPNG_STATIC=ON',
                '-DPNG_SHARED=OFF',
                '-DPNG_TESTS=OFF',
            )
            ->build();

        // libpng16_static.lib to libpng_a.lib
        FileSystem::copy("{$lib->getLibDir()}\\libpng16_static.lib", "{$lib->getLibDir()}\\libpng_a.lib");
    }
}
