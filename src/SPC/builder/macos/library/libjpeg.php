<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libjpeg extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libjpeg;

    public const NAME = 'libjpeg';
}
