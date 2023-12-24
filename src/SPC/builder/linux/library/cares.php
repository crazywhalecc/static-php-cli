<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class cares extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\cares;

    public const NAME = 'cares';
}
