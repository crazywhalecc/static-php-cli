<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class onig extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\onig;

    public const NAME = 'onig';
}
