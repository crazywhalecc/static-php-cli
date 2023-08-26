<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class libxslt extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libxslt;

    public const NAME = 'libxslt';
}
