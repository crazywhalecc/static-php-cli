<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('maxminddb')]
class maxminddb extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!is_dir(SOURCE_PATH . '/php-src/ext/maxminddb')) {
            $original = $this->source_dir;
            FileSystem::copyDir($original . '/ext', SOURCE_PATH . '/php-src/ext/maxminddb');
            $this->source_dir = SOURCE_PATH . '/php-src/ext/maxminddb';
            return true;
        }
        $this->source_dir = SOURCE_PATH . '/php-src/ext/maxminddb';
        return false;
    }
}
