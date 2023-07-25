<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

/**
 * gmp is a template library class for unix
 */
class libmemcached extends LinuxLibraryBase
{
    public const NAME = 'libmemcached';

    public function build()
    {
        throw new RuntimeException('libmemcached is currently not supported on Linux platform');
    }
}
