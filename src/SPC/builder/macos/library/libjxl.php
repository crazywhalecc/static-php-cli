<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libjxl extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libjxl;

    public const NAME = 'libjxl';
}
