<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libheif extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libheif;

    public const NAME = 'libheif';
}
