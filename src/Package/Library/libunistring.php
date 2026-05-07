<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('libunistring')]
class libunistring
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure('--disable-nls')
            ->make();

        $lib->patchLaDependencyPrefix();
    }
}
