<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * libedit library class for macOS
 */
class libedit extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libedit;

    public const NAME = 'libedit';
}
