<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class watcher extends BSDLibraryBase
{
    use \SPC\builder\unix\library\watcher;

    public const NAME = 'watcher';
}
