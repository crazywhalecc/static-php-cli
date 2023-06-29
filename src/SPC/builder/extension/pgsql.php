<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('pgsql')]
class pgsql extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-pgsql=' . BUILD_ROOT_PATH . ' ';
    }
}
