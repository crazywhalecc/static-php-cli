<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class watcher extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\watcher;

    public const NAME = 'watcher';
}
