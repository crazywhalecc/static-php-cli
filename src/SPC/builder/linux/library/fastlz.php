<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class fastlz extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\fastlz;

    public const NAME = 'fastlz';
}
