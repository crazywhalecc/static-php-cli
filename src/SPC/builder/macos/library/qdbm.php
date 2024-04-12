<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class qdbm extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\qdbm;

    public const NAME = 'qdbm';
}
