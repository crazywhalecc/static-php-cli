<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class tidy extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\tidy;

    public const NAME = 'tidy';
}
