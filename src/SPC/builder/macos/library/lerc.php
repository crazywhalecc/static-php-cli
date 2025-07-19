<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class lerc extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\lerc;

    public const NAME = 'lerc';
}
