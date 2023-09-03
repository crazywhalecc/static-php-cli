<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class snappy extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\snappy;

    public const NAME = 'snappy';
}
