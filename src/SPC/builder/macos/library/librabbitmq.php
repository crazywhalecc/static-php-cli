<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class librabbitmq extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\librabbitmq;

    public const NAME = 'librabbitmq';
}
