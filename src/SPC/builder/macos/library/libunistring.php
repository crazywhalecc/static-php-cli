<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libunistring extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libunistring;

    public const NAME = 'libunistring';
}
