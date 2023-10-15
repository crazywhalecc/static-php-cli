<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class bzip2 extends BSDLibraryBase
{
    use \SPC\builder\unix\library\bzip2;

    public const NAME = 'bzip2';
}
