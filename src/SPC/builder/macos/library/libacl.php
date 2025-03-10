<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libacl extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libacl;

    public const NAME = 'libacl';
}
