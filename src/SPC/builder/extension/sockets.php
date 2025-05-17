<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('sockets')]
class sockets extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        if (file_exists(BUILD_INCLUDE_PATH . 'php/ext/sockets/php_sockets.h')) {
            return false;
        }
        copy(SOURCE_PATH . '/php-src/ext/sockets/php_sockets.h', BUILD_INCLUDE_PATH . 'php/ext/sockets/php_sockets.h');
        return true;
    }
}
