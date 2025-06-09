<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

trait librabbitmq
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->addConfigureArgs('-DBUILD_STATIC_LIBS=ON')->build();
    }
}
