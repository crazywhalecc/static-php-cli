<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('lz4')]
class lz4 extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-lz4 --with-lz4-includedir=' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--enable-lz4';
    }
}
