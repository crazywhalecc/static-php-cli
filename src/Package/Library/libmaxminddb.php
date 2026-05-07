<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libmaxminddb')]
class libmaxminddb
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DBUILD_TESTING=OFF',
                '-DMAXMINDDB_BUILD_BINARIES=OFF',
            )
            ->build();
    }

    #[BuildFor('Windows')]
    public function buildWindows(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '-DBUILD_TESTING=OFF',
                '-DMAXMINDDB_BUILD_BINARIES=OFF',
            )
            ->build();
        if (!file_exists($lib->getLibDir() . '\libmaxminddb.lib')) {
            FileSystem::copy("{$lib->getLibDir()}\\maxminddb.lib", "{$lib->getLibDir()}\\libmaxminddb.lib");
        }
    }
}
