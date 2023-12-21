<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class leveldb extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\leveldb;

    public const NAME = 'leveldb';
}
