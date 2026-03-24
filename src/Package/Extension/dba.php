<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;

#[Extension('dba')]
class dba
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $qdbm = ($qdbm = $installer->getLibraryPackage('qdbm')) ? (" --with-qdbm={$qdbm->getBuildRootPath()}") : '';
        return '--enable-dba' . ($shared ? '=shared' : '') . $qdbm;
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(PackageInstaller $installer): string
    {
        $qdbm = $installer->getLibraryPackage('qdbm') ? ' --with-qdbm' : '';
        return '--with-dba' . $qdbm;
    }
}
