<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\builder\unix\library\postgresql as pgsql;

class postgresql extends MacOSLibraryBase
{
    use pgsql;

    public const NAME = 'postgresql';
}
