<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libaom extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libaom;

    public const NAME = 'libaom';
}
