<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('xlswriter')]
class xlswriter extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--with-xlswriter --enable-reader';
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= ' --with-openssl=' . $installer->getLibraryPackage('openssl')->getBuildRootPath();
        }
        return $arg;
    }
}
