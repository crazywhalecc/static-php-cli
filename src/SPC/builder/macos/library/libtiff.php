<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libtiff extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libtiff;

    public const NAME = 'libtiff';
}
