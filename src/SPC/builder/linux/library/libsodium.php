<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libsodium extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libsodium;

    public const NAME = 'libsodium';
}
