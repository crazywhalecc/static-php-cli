<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('pdo_odbc')]
class pdo_odbc extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-pdo_odbc')]
    public function patchBeforeBuildconf(): void
    {
        FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", 'PDO_ODBC_LDFLAGS="$pdo_odbc_def_ldflags', 'PDO_ODBC_LDFLAGS="-liconv $pdo_odbc_def_ldflags');
    }

    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getUnixConfigureArg(bool $shared): string
    {
        return '--with-pdo-odbc=' . ($shared ? 'shared,' : '') . 'unixODBC,' . BUILD_ROOT_PATH;
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(bool $shared): string
    {
        return '--with-pdo-odbc';
    }
}
