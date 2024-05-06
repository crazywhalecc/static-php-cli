<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class xz extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\xz;

    public const NAME = 'xz';
}
