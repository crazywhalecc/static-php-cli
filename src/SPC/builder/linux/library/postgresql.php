<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\unix\library\postgresql as pgsql;

class postgresql extends LinuxLibraryBase
{
    use pgsql;

    public const NAME = 'postgresql';
}
