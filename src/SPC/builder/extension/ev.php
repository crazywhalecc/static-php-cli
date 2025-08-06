<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('ev')]
class ev extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        /*
         * replace EXTENSION('ev', php_ev_sources, true, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');
         * to EXTENSION('ev', php_ev_sources, PHP_EV_SHARED, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');
         */
        FileSystem::replaceFileLineContainsString(
            $this->source_dir . '/config.w32',
            'EXTENSION(\'ev\'',
            "		EXTENSION('ev', php_ev_sources, PHP_EV_SHARED, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');"
        );
        return true;
    }
}
