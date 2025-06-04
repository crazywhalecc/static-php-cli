<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class nghttp3 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\nghttp3;

    public const NAME = 'nghttp3';
}
