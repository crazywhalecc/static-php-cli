<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libwebp extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libwebp;

    public const NAME = 'libwebp';
}
