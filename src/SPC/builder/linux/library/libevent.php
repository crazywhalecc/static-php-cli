<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libevent extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libevent;

    public const NAME = 'libevent';
}
