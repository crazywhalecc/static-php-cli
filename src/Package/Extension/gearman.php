<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;

#[Extension('gearman')]
class gearman
{
    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        return '--with-gearman=' . ($shared ? 'shared,' : '') . $installer->getLibraryPackage('libgearman')->getBuildRootPath();
    }
}
