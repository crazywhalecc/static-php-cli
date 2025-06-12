<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\util\executor\UnixCMakeExecutor;

class libmemcached extends MacOSLibraryBase
{
    public const NAME = 'libmemcached';

    public function build(): void
    {
        UnixCMakeExecutor::create($this)->build();
    }
}
