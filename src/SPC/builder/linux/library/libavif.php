<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libavif extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libavif;

    public const NAME = 'libavif';
}
