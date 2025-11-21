<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libunistring extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libunistring;

    public const NAME = 'libunistring';
}
