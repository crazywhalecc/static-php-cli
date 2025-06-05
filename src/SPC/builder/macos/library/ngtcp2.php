<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class ngtcp2 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\ngtcp2;

    public const NAME = 'ngtcp2';
}
