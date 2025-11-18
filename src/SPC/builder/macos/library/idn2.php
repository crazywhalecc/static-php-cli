<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class idn2 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\idn2;

    public const NAME = 'idn2';
}
