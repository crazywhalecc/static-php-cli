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
        if (file_exists(SOURCE_PATH . '/php-src/ext/glfw')) {
            return false;
        }
        FileSystem::copyDir(SOURCE_PATH . '/ext-glfw', SOURCE_PATH . '/php-src/ext/glfw');
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lglfw ', '-lglfw3 ');

        // ogt_vox_c_wrapper.cpp requires the C++ standard library
        $cxxLib = PHP_OS_FAMILY === 'Darwin' ? '-lc++' : '-lstdc++';
        $extraLibs = trim($this->builder->getOption('extra-libs', '') . ' ' . $cxxLib);
        $this->builder->setOption('extra-libs', $extraLibs);

        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-glfw --with-glfw-dir=' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--enable-glfw=static';
    }
}
