<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class mimalloc extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\mimalloc;

    public const NAME = 'mimalloc';
}
