<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;

#[Library('nghttp3')]
class nghttp3
{
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DENABLE_SHARED_LIB=OFF',
                '-DENABLE_STATIC_LIB=ON',
                '-DBUILD_STATIC_LIBS=ON',
                '-DBUILD_SHARED_LIBS=OFF',
                '-DENABLE_STATIC_CRT=ON',
                '-DENABLE_LIB_ONLY=ON',
            )
            ->build();
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure('--enable-lib-only')
            ->make();

        $lib->patchPkgconfPrefix(['libnghttp3.pc'], PKGCONF_PATCH_PREFIX);
    }
}
