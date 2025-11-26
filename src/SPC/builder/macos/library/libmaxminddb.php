<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libmaxminddb extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libmaxminddb;

    public const NAME = 'libmaxminddb';
}
