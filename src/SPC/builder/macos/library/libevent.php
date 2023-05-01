<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libevent extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libevent;

    public const NAME = 'libevent';
}
