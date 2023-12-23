<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class liblz4 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\liblz4;

    public const NAME = 'liblz4';
}
