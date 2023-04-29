<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libzip extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libzip;

    public const NAME = 'libzip';
}
