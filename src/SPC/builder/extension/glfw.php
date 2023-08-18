<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('glfw')]
class glfw extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::copyDir(SOURCE_PATH . '/ext-glfw', SOURCE_PATH . '/php-src/ext/glfw');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-glfw --with-glfw-dir=' . BUILD_ROOT_PATH;
    }
}
