<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

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
}
