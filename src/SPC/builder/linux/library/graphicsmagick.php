<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class graphicsmagick extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\graphicsmagick;

    public const NAME = 'graphicsmagick';
}
