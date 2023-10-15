<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class zlib extends BSDLibraryBase
{
    use \SPC\builder\unix\library\zlib;

    public const NAME = 'zlib';
}
