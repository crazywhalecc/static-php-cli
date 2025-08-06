<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('bz2')]
class bz2 extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        $frameworks = $this->builder instanceof MacOSBuilder ? ' ' . $this->builder->getFrameworks(true) . ' ' : '';
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/-lbz2/', $this->getLibFilesString() . $frameworks);
        return true;
    }
}
