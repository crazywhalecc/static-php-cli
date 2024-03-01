<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libuuid extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libuuid;

    public const NAME = 'libuuid';
}
