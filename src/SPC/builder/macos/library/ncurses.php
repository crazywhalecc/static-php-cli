<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class ncurses extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\ncurses;

    public const NAME = 'ncurses';
}
