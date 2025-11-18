<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class psl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\psl;

    public const NAME = 'psl';
}
