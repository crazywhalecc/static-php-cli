<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libheif extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libheif;

    public const NAME = 'libheif';
}
