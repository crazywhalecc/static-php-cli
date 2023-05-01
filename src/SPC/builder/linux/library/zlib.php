<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class zlib extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\zlib;

    public const NAME = 'zlib';
}
