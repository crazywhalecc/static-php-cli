<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class librabbitmq extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\librabbitmq;

    public const NAME = 'librabbitmq';
}
