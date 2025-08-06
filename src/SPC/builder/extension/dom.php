<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('dom')]
class dom extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-dom' . ($shared ? '=shared' : '');
        $arg .= ' --with-libxml="' . BUILD_ROOT_PATH . '"';
        return $arg;
    }

    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/win32/build/config.w32', 'dllmain.c ', '');
        return true;
    }

    public function getWindowsConfigureArg($shared = false): string
    {
        return '--with-dom';
    }
}
