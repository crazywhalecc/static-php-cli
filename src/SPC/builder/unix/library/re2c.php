<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait re2c
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->build();
    }
}
