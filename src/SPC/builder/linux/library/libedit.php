<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libedit extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libedit;

    public const NAME = 'libedit';
}
