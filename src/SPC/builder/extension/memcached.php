<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('memcached')]
class memcached extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-memcached' . ($shared ? '=shared' : '') . ' ' .
            '--with-zlib-dir=' . BUILD_ROOT_PATH . ' ' .
            '--with-libmemcached-dir=' . BUILD_ROOT_PATH . ' ' .
            '--disable-memcached-sasl ' .
            '--enable-memcached-json ' .
            '--with-system-fastlz';
    }
}
