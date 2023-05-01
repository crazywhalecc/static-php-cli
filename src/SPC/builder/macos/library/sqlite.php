<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class sqlite extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\sqlite;

    public const NAME = 'sqlite';
}
