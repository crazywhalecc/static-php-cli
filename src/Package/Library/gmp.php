<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;

#[Library('gmp')]
class gmp
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        $make = UnixAutoconfExecutor::create($lib)->appendEnv(['CFLAGS' => '-std=c17']);
        if (SystemTarget::getTargetArch() === 'x86_64' && SystemTarget::getTargetOS() === 'Linux') {
            $libc = SystemTarget::getLibc() === 'glibc' ? 'gnu' : 'musl';
            $make->addConfigureArgs(["--host=x86_64-pc-linux-{$libc}"]);
        }
        $make->configure('--enable-fat')->make();
        $lib->patchPkgconfPrefix(['gmp.pc']);
    }
}
