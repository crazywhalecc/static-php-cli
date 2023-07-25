<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('memcache')]
class memcache extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--enable-memcache --with-zlib-dir=' . BUILD_ROOT_PATH;
    }
}
