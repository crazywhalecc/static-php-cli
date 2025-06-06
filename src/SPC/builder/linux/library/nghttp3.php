<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class nghttp3 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\nghttp3;

    public const NAME = 'nghttp3';
}
