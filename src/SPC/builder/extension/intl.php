<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\windows\WindowsBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('intl')]
class intl extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder instanceof WindowsBuilder) {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/intl/config.w32',
                'EXTENSION("intl", "php_intl.c intl_convert.c intl_convertcpp.cpp intl_error.c ", true,',
                'EXTENSION("intl", "php_intl.c intl_convert.c intl_convertcpp.cpp intl_error.c ", PHP_INTL_SHARED,'
            );
            return true;
        }
        return false;
    }

    public function patchBeforeSharedPhpize(): bool
    {
        return $this->patchBeforeBuildconf();
    }
}
