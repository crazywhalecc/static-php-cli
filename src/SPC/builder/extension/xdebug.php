<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('xdebug')]
class xdebug extends Extension
{
    protected function isZendExtension(): bool
    {
        return true;
    }
}
