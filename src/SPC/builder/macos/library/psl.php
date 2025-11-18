<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class psl extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\psl;

    public const NAME = 'psl';
}
