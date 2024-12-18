<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libde265 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libde265;

    public const NAME = 'libde265';
}
