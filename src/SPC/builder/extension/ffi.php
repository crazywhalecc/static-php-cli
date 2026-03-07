<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\linux\SystemUtil;
use SPC\store\SourcePatcher;
use SPC\util\CustomExt;

#[CustomExt('ffi')]
class ffi extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (PHP_OS_FAMILY === 'Linux' && SystemUtil::getOSRelease()['dist'] === 'centos') {
            return SourcePatcher::patchFfiCentos7FixO3strncmp();
        }
        return false;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-ffi' . ($shared ? '=shared' : '') . ' --enable-zend-signals';
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--with-ffi';
    }
}
