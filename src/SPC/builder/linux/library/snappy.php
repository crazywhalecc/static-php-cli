<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class snappy extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\snappy;

    public const NAME = 'snappy';
}
