<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libssh2 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libssh2;

    public const NAME = 'libssh2';
}
