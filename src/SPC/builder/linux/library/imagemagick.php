<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * a template library class for unix
 */
class imagemagick extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\imagemagick;

    public const NAME = 'imagemagick';
}
