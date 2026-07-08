<?php

declare(strict_types=1);

namespace Package\Tool;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\Tool;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\ToolPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;

#[Tool('pkg-config')]
class pkgconfig
{
    #[InitPackage]
    public function resolveBuild(): void
    {
        ApplicationContext::set('elephant', true);
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(ToolPackage $package, ToolchainInterface $toolchain): void
    {
        UnixAutoconfExecutor::create($package)
            ->appendEnv([
                'CFLAGS' => '-Wimplicit-function-declaration -Wno-int-conversion',
                'LDFLAGS' => $toolchain->isStatic() ? '--static' : '',
            ])
            ->configure(
                '--with-internal-glib',
                '--disable-host-tool',
                '--without-sysroot',
                '--without-system-include-path',
                '--without-system-library-path',
                '--without-pc-path',
            )
            ->make(with_install: 'install-exec');

        shell()->exec("strip {$package->getBinDir()}/pkg-config");
    }
}
