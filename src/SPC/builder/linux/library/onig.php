<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class onig extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\onig;

    public const NAME = 'onig';
}
