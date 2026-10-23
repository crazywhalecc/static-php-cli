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
    private const array DISABLE_ARGS = [
        '-DBUILD_EXAMPLES=OFF',
        '-DBUILD_TESTING=OFF',
        '-DBUILD_TOOLS=OFF',
        '-DBUILD_TOOLS_DOCS=OFF',
        '-DBUILD_API_DOCS=OFF',
    ];

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DBUILD_STATIC_LIBS=ON', ...self::DISABLE_ARGS)
            ->build();
    }

    #[BuildFor('Windows')]
    public function buildWin(): void
    {
        WindowsCMakeExecutor::create($this)
            ->addConfigureArgs(...self::DISABLE_ARGS)
            ->build();
        rename("{$this->getLibDir()}\\librabbitmq.4.lib", "{$this->getLibDir()}\\rabbitmq.4.lib");
    }
}
