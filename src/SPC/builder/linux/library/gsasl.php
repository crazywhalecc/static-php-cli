<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class gsasl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\gsasl;

    public const NAME = 'gsasl';
}
