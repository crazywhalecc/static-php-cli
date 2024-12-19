<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libaom extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libaom;

    public const NAME = 'libaom';
}
