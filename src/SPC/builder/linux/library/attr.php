<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class attr extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\attr;

    public const NAME = 'attr';
}
