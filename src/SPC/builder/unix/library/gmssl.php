<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;

trait gmssl
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->build();
    }
}
