<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait librabbitmq
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->addConfigureArgs('-DBUILD_STATIC_LIBS=ON')->build();
    }
}
