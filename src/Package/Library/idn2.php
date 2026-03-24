<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('idn2')]
class idn2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure(
                '--disable-nls',
                '--disable-doc',
                '--enable-year2038',
                '--disable-rpath'
            )
            ->optionalPackage('libiconv', '--with-libiconv-prefix=' . BUILD_ROOT_PATH)
            ->optionalPackage('libunistring', '--with-libunistring-prefix=' . BUILD_ROOT_PATH)
            ->optionalPackage('gettext', '--with-libnintl-prefix=' . BUILD_ROOT_PATH)
            ->make();
        $lib->patchPkgconfPrefix(['libidn2.pc']);
        $lib->patchLaDependencyPrefix();
    }
}
