<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('gmssl')]
class gmssl extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (str_contains(file_get_contents(SOURCE_PATH . '/php-src/ext/gmssl/config.w32'), 'CHECK_LIB(')) {
            return false;
        }
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/gmssl/config.w32', 'AC_DEFINE(', 'CHECK_LIB("gmssl.lib", "gmssl", PHP_GMSSL);' . PHP_EOL . 'AC_DEFINE(');
        return true;
    }
}
