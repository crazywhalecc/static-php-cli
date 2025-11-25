<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('trader')]
class trader extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/trader/config.m4', 'PHP_TA', 'PHP_TRADER');
        return true;
    }
}
