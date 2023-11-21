<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class tidy extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\tidy;

    public const NAME = 'tidy';
}
