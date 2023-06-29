<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\unix\library\libxml2 as libxml2Trait;

class libxml2 extends LinuxLibraryBase
{
    use libxml2Trait;

    public const NAME = 'libxml2';
}
