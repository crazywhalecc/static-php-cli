<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('lcms2')]
class lcms2
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure()
            ->make();

        $lib->patchPkgconfPrefix(['lcms2.pc']);
        $lib->patchLaDependencyPrefix();
    }
}
