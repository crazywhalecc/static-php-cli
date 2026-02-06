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
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure(
                '--enable-extra-encodings',
                '--enable-year2038',
            )
            ->make('install-lib', with_install: false)
            ->make('install-lib', with_install: false, dir: $lib->getSourceDir() . '/libcharset');
        $lib->patchLaDependencyPrefix();
    }
}
