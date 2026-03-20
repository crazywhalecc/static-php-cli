<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;

#[Library('zstd')]
class zstd
{
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $package): void
    {
        WindowsCMakeExecutor::create($package)
            ->setRootDir("{$package->getSourceDir()}/build/cmake")
            ->setBuildDir("{$package->getSourceDir()}/build/cmake/build")
            ->addConfigureArgs(
                '-DZSTD_BUILD_STATIC=ON',
                '-DZSTD_BUILD_SHARED=OFF',
            )
            ->build();
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/build/cmake/build")
            ->addConfigureArgs(
                '-DZSTD_BUILD_STATIC=ON',
                '-DZSTD_BUILD_SHARED=OFF',
            )
            ->build();

        $lib->patchPkgconfPrefix(['libzstd.pc'], PKGCONF_PATCH_PREFIX);
    }
}
