<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('maxminddb')]
class maxminddb extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!is_link(SOURCE_PATH . '/php-src/ext/maxminddb')) {
            $original = $this->source_dir;
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D maxminddb ' . $original . '\ext');
            } else {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s ' . $original . '/ext maxminddb');
            }
            return true;
        }
        return false;
    }
}
