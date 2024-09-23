<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libcares extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libcares;

    public const NAME = 'libcares';
}
