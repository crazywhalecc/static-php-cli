<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class zstd extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\zstd;

    public const NAME = 'zstd';
}
