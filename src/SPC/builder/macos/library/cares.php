<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\builder\linux\library\LinuxLibraryBase;

class cares extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\cares;

    public const NAME = 'cares';
}
