<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('opcache')]
class opcache extends Extension
{
    public function getDistName(): string
    {
        return 'Zend Opcache';
    }
}
