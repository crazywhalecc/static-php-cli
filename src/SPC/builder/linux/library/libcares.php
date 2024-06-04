<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libcares extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libcares;

    public const NAME = 'libcares';
}
