<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class gmssl extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\gmssl;

    public const NAME = 'gmssl';
}
