<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('libsodium')]
class libsodium
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure()->make();

        // Patch pkg-config file
        $lib->patchPkgconfPrefix(['libsodium.pc'], PKGCONF_PATCH_PREFIX);
    }
}
