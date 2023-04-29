<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libyaml extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libyaml;

    public const NAME = 'libyaml';
}
