<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class onig extends BSDLibraryBase
{
    use \SPC\builder\unix\library\onig;

    public const NAME = 'onig';
}
