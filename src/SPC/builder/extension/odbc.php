<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('odbc')]
class odbc extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-unixODBC=' . BUILD_ROOT_PATH;
    }
}
