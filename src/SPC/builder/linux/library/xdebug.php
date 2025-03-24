<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\util\DynamicExt;

#[DynamicExt]
class xdebug extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\xdebug;

    public const NAME = 'xdebug';
}
