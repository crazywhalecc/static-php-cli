<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;

#[Library('libmemcached')]
class libmemcached extends LibraryPackage
{
    #[BuildFor('Linux')]
    public function buildLinux(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DCMAKE_INSTALL_RPATH=""')
            ->build();
    }

    #[BuildFor('Darwin')]
    public function buildDarwin(): void
    {
        UnixCMakeExecutor::create($this)->build();
    }
}
