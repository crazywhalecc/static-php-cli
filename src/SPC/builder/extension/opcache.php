<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\SourcePatcher;
use SPC\util\CustomExt;

#[CustomExt('opcache')]
class opcache extends Extension
{
    /**
     * @throws WrongUsageException
     * @throws RuntimeException
     */
    public function validate(): void
    {
        if ($this->builder->getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new WrongUsageException('Statically compiled PHP with Zend Opcache only available for PHP >= 8.0 !');
        }
    }

    public function patchBeforeBuildconf(): bool
    {
        if (file_exists(SOURCE_PATH . '/php-src/.opcache_patched')) {
            return false;
        }
        // if 8.2.0 <= PHP_VERSION < 8.2.23, we need to patch from legacy patch file
        if (version_compare($this->builder->getPHPVersion(), '8.2.0', '>=') && version_compare($this->builder->getPHPVersion(), '8.2.23', '<')) {
            SourcePatcher::patchFile('spc_fix_static_opcache_before_80222.patch', SOURCE_PATH . '/php-src');
        }
        // if 8.3.0 <= PHP_VERSION < 8.3.11, we need to patch from legacy patch file
        elseif (version_compare($this->builder->getPHPVersion(), '8.3.0', '>=') && version_compare($this->builder->getPHPVersion(), '8.3.11', '<')) {
            SourcePatcher::patchFile('spc_fix_static_opcache_before_80310.patch', SOURCE_PATH . '/php-src');
        } else {
            SourcePatcher::patchMicro(items: ['static_opcache']);
        }
        return file_put_contents(SOURCE_PATH . '/php-src/.opcache_patched', '1') !== false;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-opcache';
    }

    public function getDistName(): string
    {
        return 'Zend Opcache';
    }
}
