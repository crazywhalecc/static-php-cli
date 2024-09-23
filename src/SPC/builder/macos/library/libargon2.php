<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libargon2 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libargon2;

    public const NAME = 'libargon2';
}
