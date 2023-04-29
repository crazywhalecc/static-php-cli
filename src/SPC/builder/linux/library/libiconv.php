<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libiconv extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libiconv;

    public const NAME = 'libiconv';
}
