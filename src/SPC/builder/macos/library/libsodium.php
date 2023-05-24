<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libsodium extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libsodium;

    public const NAME = 'libsodium';
}
