<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\SharedLibraryUtil;

#[Library('libuv')]
class libuv
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(SharedLibraryUtil::buildsShared($lib) ? '-DLIBUV_BUILD_SHARED=ON' : '-DLIBUV_BUILD_SHARED=OFF')
            ->build();
        // patch pkgconfig
        $lib->patchPkgconfPrefix(SharedLibraryUtil::buildsShared($lib) ? ['libuv.pc', 'libuv-static.pc'] : ['libuv-static.pc']);
    }

    #[BuildFor('Windows')]
    public function buildWindows(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs('-DLIBUV_BUILD_SHARED=OFF')
            ->build();
    }
}
