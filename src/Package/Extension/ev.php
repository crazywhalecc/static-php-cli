<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('ev')]
class ev extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-ev')]
    public function patchBeforeBuildconf(): bool
    {
        /*
         * replace EXTENSION('ev', php_ev_sources, true, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');
         * to EXTENSION('ev', php_ev_sources, PHP_EV_SHARED, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');
         */
        FileSystem::replaceFileLineContainsString(
            "{$this->getSourceDir()}\\config.w32",
            'EXTENSION(\'ev\'',
            "		EXTENSION('ev', php_ev_sources, PHP_EV_SHARED, ' /DZEND_ENABLE_STATIC_TSRMLS_CACHE=1');"
        );
        return true;
    }
}
