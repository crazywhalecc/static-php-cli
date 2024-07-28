<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('rar')]
class rar extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeBuildconf(): bool
    {
        // workaround for newer Xcode clang (>= 15.0)
        if ($this->builder instanceof MacOSBuilder) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/rar/config.m4', '-Wall -fvisibility=hidden', '-Wall -Wno-incompatible-function-pointer-types -fvisibility=hidden');
            return true;
        }
        return false;
    }
}
