<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class readline extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\readline;

    public const NAME = 'readline';
}
