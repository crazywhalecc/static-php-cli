<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('decimal')]
class decimal extends Extension
{
    // TODO: remove this when https://github.com/php-decimal/ext-decimal/issues/92 is merged
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/php_decimal.c',
            'zend_module_entry decimal_module_entry',
            'zend_module_entry php_decimal_module_entry'
        );
        FileSystem::replaceFileStr(
            $this->source_dir . '/config.w32',
            'ARG_WITH("decimal", "for decimal support", "no");',
            'ARG_WITH("decimal", "for decimal support",  "no");' . "\n" .
            'ADD_EXTENSION_DEP("decimal", "json");'
        );
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-decimal --with-libmpdec-path="' . BUILD_ROOT_PATH . '"';
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--with-decimal';
    }
}
