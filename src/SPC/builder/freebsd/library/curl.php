<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class curl extends BSDLibraryBase
{
    use \SPC\builder\unix\library\curl;

    public const NAME = 'curl';
}
