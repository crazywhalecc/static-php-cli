<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
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

    public function getUnixConfigureArg(): string
    {
        return '--enable-opcache';
    }

    public function getDistName(): string
    {
        return 'Zend Opcache';
    }
}
