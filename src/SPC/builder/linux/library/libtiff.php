<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libtiff extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libtiff;

    public const NAME = 'libtiff';
}
