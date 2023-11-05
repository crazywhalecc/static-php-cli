<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class postgresql extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\postgresql;

    public const NAME = 'postgresql';
}
