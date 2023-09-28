<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libpam extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libpam;

    public const NAME = 'libpam';
}
