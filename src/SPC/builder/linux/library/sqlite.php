<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class sqlite extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\sqlite;

    public const NAME = 'sqlite';
}
