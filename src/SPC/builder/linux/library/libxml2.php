<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libxml2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libxml2;

    public const NAME = 'libxml2';
}
