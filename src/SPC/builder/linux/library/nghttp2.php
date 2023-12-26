<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class nghttp2 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\nghttp2;

    public const NAME = 'nghttp2';
}
