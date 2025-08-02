<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * a template library class for unix
 */
class re2c extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\re2c;

    public const NAME = 're2c';
}
