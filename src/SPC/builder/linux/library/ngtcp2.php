<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class ngtcp2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\ngtcp2;

    public const NAME = 'ngtcp2';
}
