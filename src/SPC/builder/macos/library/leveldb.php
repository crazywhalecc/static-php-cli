<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class leveldb extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\leveldb;

    public const NAME = 'leveldb';
}
