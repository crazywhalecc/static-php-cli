<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;

#[Library('librabbitmq')]
class librabbitmq extends LibraryPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(): void
    {
        UnixCMakeExecutor::create($this)->addConfigureArgs('-DBUILD_STATIC_LIBS=ON')->build();
    }

    #[BuildFor('Windows')]
    public function buildWin(): void
    {
        WindowsCMakeExecutor::create($this)->build();
        rename("{$this->getLibDir()}\\librabbitmq.4.lib", "{$this->getLibDir()}\\rabbitmq.4.lib");
    }
}
