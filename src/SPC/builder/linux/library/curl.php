<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class curl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\curl;

    public const NAME = 'curl';
}
