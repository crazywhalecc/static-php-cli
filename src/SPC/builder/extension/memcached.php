<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('memcached')]
class memcached extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $rootdir = BUILD_ROOT_PATH;
        return "--enable-memcached --with-zlib-dir={$rootdir} --with-libmemcached-dir={$rootdir} --disable-memcached-sasl --enable-memcached-json";
    }
}
