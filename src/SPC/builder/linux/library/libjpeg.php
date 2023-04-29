<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libjpeg extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libjpeg;

    public const NAME = 'libjpeg';
}
