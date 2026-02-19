<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;

#[Library('snappy')]
class snappy
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/cmake/build")
            ->addConfigureArgs(
                '-DSNAPPY_BUILD_TESTS=OFF',
                '-DSNAPPY_BUILD_BENCHMARKS=OFF',
            )
            ->build('../..');
    }
}
