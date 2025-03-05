<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libacl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libacl;

    public const NAME = 'libacl';
}
