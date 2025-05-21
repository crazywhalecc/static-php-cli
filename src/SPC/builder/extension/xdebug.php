<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('xdebug')]
class xdebug extends Extension
{
    public function runSharedExtensionCheckUnix(): void
    {
        [$ret, $out] = shell()->execWithResult(BUILD_BIN_PATH . '/php -n -d "zend_extension=' . BUILD_MODULES_PATH . '/xdebug.so" -v');
        if ($ret !== 0) {
            throw new RuntimeException('xdebug.so failed to load.');
        }
        if (!str_contains(join($out), 'with Xdebug')) {
            throw new RuntimeException('xdebug.so failed to load.');
        }
    }
}
