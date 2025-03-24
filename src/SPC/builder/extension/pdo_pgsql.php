<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('pdo_pgsql')]
class pdo_pgsql extends Extension
{
    public function getWindowsConfigureArg(): string
    {
        return '--with-pdo-pgsql=yes';
    }
}
