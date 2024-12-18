<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libde265 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libde265;

    public const NAME = 'libde265';
}
