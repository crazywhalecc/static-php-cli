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
        return '--enable-lz4' . ($shared ? '=shared' : '') . ' --with-lz4-includedir=' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--enable-lz4';
    }
}
