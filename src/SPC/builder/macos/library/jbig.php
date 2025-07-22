<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class jbig extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\jbig;

    public const NAME = 'jbig';
}
