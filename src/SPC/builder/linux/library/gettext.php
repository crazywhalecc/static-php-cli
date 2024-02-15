<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class gettext extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\gettext;

    public const NAME = 'gettext';
}
