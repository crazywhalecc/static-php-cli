<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

/**
 * gmp is a template library class for unix
 */
class pkgconfig extends BSDLibraryBase
{
    use \SPC\builder\unix\library\pkgconfig;

    public const NAME = 'pkg-config';
}
