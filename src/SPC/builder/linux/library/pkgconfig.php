<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * gmp is a template library class for unix
 */
class pkgconfig extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\pkgconfig;

    public const NAME = 'pkg-config';
}
