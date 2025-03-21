<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('pdo_odbc')]
class pdo_odbc extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-pdo-odbc=unixODBC,' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--with-pdo-odbc';
    }
}
