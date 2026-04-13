<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class libmpdec extends BSDLibraryBase
{
    use \SPC\builder\unix\library\libmpdec;

    public const NAME = 'libmpdec';
}
