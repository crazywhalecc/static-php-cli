<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class jbig extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\jbig;

    public const NAME = 'jbig';
}
