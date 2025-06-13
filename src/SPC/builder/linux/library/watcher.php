<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class watcher extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\watcher;

    public const NAME = 'watcher';
}
