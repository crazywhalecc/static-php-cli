<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class lerc extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\lerc;

    public const NAME = 'lerc';
}
