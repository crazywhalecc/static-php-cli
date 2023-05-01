<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class freetype extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\freetype;

    public const NAME = 'freetype';
}
