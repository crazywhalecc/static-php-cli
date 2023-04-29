<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class xz extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\xz;

    public const NAME = 'xz';
}
