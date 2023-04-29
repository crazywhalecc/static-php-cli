<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class bzip2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\bzip2;

    public const NAME = 'bzip2';
}
