<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libxml2 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libxml2;

    public const NAME = 'libxml2';
}
