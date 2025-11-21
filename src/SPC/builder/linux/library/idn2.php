<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class idn2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\idn2;

    public const NAME = 'idn2';
}
