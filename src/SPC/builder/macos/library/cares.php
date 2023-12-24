<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class cares extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\cares;

    public const NAME = 'cares';
}
