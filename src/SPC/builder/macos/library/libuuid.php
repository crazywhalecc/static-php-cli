<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libuuid extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libuuid;

    public const NAME = 'libuuid';
}
