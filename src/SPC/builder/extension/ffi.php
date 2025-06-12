<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('ffi')]
class ffi extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-ffi' . ($shared ? '=shared' : '') . ' --enable-zend-signals';
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--with-ffi';
    }
}
