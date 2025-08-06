<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('uv')]
class uv extends Extension
{
    public function validate(): void
    {
        if ($this->builder->getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new ValidationException('The latest uv extension requires PHP 8.0 or later');
        }
    }

    public function patchBeforeSharedMake(): bool
    {
        parent::patchBeforeSharedMake();
        if (PHP_OS_FAMILY !== 'Linux' || arch2gnu(php_uname('m')) !== 'aarch64') {
            return false;
        }
        FileSystem::replaceFileRegex($this->source_dir . '/Makefile', '/^(LDFLAGS =.*)$/m', '$1 -luv -ldl -lrt -pthread');
        return true;
    }
}
