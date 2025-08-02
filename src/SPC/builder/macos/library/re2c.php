<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class re2c extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\re2c;

    public const NAME = 're2c';
}
