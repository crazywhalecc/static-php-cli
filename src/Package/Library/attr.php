<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('attr')]
class attr
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->appendEnv([
                'CFLAGS' => '-Wno-int-conversion -Wno-implicit-function-declaration',
            ])
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->configure('--disable-nls')
            ->make('install-attributes_h install-data install-libattr_h install-libLTLIBRARIES install-pkgincludeHEADERS install-pkgconfDATA', with_install: false);
        $lib->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
