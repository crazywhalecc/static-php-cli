<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class readline extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\readline;

    public const NAME = 'readline';
}
