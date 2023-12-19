<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libargon2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libargon2;

    public const NAME = 'libargon2';
}
