<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('zstd')]
class zstd extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--enable-zstd --with-libzstd="' . BUILD_ROOT_PATH . '"';
    }
}
