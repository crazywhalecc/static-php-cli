<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('xlswriter')]
class xlswriter extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-xlswriter --enable-reader';
    }
}
