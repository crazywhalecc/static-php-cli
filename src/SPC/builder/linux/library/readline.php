<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class readline extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\readline;

    public const NAME = 'readline';
}
