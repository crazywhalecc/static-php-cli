<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libuv extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libuv;

    public const NAME = 'libuv';
}
