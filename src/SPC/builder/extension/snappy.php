<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('snappy')]
class snappy extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--enable-snappy --with-snappy-includedir="' . BUILD_ROOT_PATH . '"';
    }
}
