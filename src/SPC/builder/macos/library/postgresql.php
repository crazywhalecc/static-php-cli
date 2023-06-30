<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class postgresql extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\postgresql;

    public const NAME = 'postgresql';
}
