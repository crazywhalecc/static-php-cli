<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('libiconv')]
class libiconv
{
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $package): void
    {
        UnixAutoconfExecutor::create($package)
            ->configure(
                '--enable-extra-encodings',
                '--enable-year2038',
            )
            ->make('install-lib', with_install: false)
            ->make('install-lib', with_install: false, dir: "{$package->getSourceDir()}/libcharset");
        $package->patchLaDependencyPrefix();
    }
}
