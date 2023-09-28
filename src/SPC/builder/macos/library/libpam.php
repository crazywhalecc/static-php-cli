<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libpam extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libpam;

    public const NAME = 'libpam';
}
