<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class grpc extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\grpc;

    public const NAME = 'grpc';
}
