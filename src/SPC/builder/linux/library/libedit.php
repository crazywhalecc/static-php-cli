<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * libedit library class for linux
 */
class libedit extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libedit;

    public const NAME = 'libedit';
}
