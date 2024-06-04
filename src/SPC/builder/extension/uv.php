<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('uv')]
class uv extends Extension
{
    public function validate(): void
    {
        if ($this->builder->getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new \RuntimeException('The latest uv extension requires PHP 8.0 or later');
        }
    }
}
