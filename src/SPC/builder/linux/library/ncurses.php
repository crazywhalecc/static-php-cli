<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * gmp is a template library class for unix
 */
class ncurses extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\ncurses;

    public const NAME = 'ncurses';
}
