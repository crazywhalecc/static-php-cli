<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libuv extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libuv;

    public const NAME = 'libuv';
}
