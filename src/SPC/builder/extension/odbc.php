<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('odbc')]
class odbc extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-unixODBC=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH;
    }
}
