<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class unixodbc extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\unixodbc;

    public const NAME = 'unixodbc';
}
