<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pdo_odbc')]
class pdo_odbc extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/pdo_odbc/config.m4', 'PDO_ODBC_LDFLAGS="$pdo_odbc_def_ldflags', 'PDO_ODBC_LDFLAGS="-liconv $pdo_odbc_def_ldflags');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--with-pdo-odbc=unixODBC,' . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--with-pdo-odbc';
    }
}
