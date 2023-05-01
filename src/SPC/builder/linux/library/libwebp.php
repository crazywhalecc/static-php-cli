<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class libwebp extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libwebp;

    public const NAME = 'libwebp';
}
