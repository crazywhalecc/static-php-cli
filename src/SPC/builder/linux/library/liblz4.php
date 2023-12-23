<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class liblz4 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\liblz4;

    public const NAME = 'liblz4';
}
