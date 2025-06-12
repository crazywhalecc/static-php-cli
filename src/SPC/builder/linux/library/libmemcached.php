<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\util\executor\UnixCMakeExecutor;

class libmemcached extends LinuxLibraryBase
{
    public const NAME = 'libmemcached';

    public function build(): void
    {
        UnixCMakeExecutor::create($this)->build();
    }
}
