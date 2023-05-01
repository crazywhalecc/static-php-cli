<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class zstd extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\zstd;

    public const NAME = 'zstd';
}
