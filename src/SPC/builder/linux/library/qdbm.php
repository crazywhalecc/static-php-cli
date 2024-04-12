<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class qdbm extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\qdbm;

    public const NAME = 'qdbm';
}
