<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('onig')]
class onig
{
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $package): void
    {
        WindowsCMakeExecutor::create($package)
            ->addConfigureArgs('-DMSVC_STATIC_RUNTIME=ON')
            ->build();
        FileSystem::copy("{$package->getLibDir()}\\onig.lib", "{$package->getLibDir()}\\onig_a.lib");
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure()->make();
        $lib->patchPkgconfPrefix(['oniguruma.pc']);
    }
}
