<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('glfw')]
class glfw extends Extension
{
    /**
     * @throws RuntimeException
     */
    public function patchBeforeBuildconf(): bool
    {
        if (file_exists(SOURCE_PATH . '/php-src/ext/glfw')) {
            return false;
        }
        FileSystem::copyDir(SOURCE_PATH . '/ext-glfw', SOURCE_PATH . '/php-src/ext/glfw');
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lglfw ', '-lglfw3 ');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-glfw --with-glfw-dir=' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--enable-glfw=static';
    }
}
