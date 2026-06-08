<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libmpdec extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libmpdec;

    public const NAME = 'libmpdec';
}
