<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('gettext')]
class gettext extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder instanceof MacOSBuilder) {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/gettext/config.m4',
                ['AC_CHECK_LIB($GETTEXT_CHECK_IN_LIB', 'AC_CHECK_LIB([$GETTEXT_CHECK_IN_LIB'],
                ['AC_CHECK_LIB(intl', 'AC_CHECK_LIB([intl'] // new php versions use a bracket
            );
        }
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        if ($this->builder instanceof MacOSBuilder) {
            $frameworks = ' ' . $this->builder->getFrameworks(true) . ' ';
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lintl', $this->getLibFilesString() . $frameworks);
        }
        return true;
    }
}
