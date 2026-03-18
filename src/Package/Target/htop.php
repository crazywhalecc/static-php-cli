<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;

#[Target('htop')]
class htop extends TargetPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(ToolchainInterface $toolchain): void
    {
        // htop's --enable-static adds -static to CFLAGS/LDFLAGS globally (not just for .a libraries),
        // which causes zig-cc to fail on glibc targets. Only enable it for truly static (musl) builds.
        UnixAutoconfExecutor::create($this)
            ->removeConfigureArgs('--disable-shared', '--enable-static')
            ->exec('./autogen.sh')
            ->addConfigureArgs($toolchain->isStatic() ? '--enable-static' : '--disable-static')
            ->configure()
            ->make(with_clean: false);
    }
}
