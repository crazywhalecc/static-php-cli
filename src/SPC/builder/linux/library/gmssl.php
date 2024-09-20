<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class gmssl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\gmssl;

    public const NAME = 'gmssl';
}
