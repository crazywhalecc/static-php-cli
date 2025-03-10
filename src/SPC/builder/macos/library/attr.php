<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class attr extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\attr;

    public const NAME = 'attr';
}
