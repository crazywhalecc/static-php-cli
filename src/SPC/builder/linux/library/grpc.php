<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class grpc extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\grpc;

    public const NAME = 'grpc';
}
