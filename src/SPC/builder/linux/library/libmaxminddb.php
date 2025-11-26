<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libmaxminddb extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libmaxminddb;

    public const NAME = 'libmaxminddb';
}
