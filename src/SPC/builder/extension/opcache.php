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
        return SourcePatcher::patchMicro(items: ['static_opcache']) && file_put_contents(SOURCE_PATH . '/php-src/.opcache_patched', '1') !== false;
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
