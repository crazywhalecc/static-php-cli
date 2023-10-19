<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('zlib')]
class zlib extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-zlib --with-zlib-dir="' . BUILD_ROOT_PATH . '"';
    }
}
