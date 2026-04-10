<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class graphicsmagick extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\graphicsmagick;

    public const NAME = 'graphicsmagick';
}
