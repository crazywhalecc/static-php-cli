<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait icu
{
    public function beforePack(): void
    {
        // Replace buildroot/bin/icu-config default_prefix=* to placeholder default_prefix={BUILD_ROOT_PATH}
        $icu_config = BUILD_ROOT_PATH . '/bin/icu-config';
        FileSystem::replaceFileRegex($icu_config, '/default_prefix=.*/m', 'default_prefix="{BUILD_ROOT_PATH}"');
    }

    protected function install(): void
    {
        $icu_config = BUILD_ROOT_PATH . '/bin/icu-config';
        FileSystem::replaceFileStr($icu_config, '{BUILD_ROOT_PATH}', BUILD_ROOT_PATH);
    }
}
