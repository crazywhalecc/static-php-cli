<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class unixodbc extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\unixodbc;

    public const NAME = 'unixodbc';
}
