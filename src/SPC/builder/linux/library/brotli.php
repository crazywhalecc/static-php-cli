<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class brotli extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\brotli;

    public const NAME = 'brotli';
}
