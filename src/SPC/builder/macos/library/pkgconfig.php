<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class pkgconfig extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\pkgconfig;

    public const NAME = 'pkg-config';
}
