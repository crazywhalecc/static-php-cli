<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;

#[Extension('zlib')]
class zlib
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function unixConfigureArg(PackageBuilder $builder, PackageInstaller $installer): string
    {
        $zlib_dir = (php::getPHPVersionID() >= 80400 && !$installer->getPhpExtensionPackage('spx')) ? '' : " --with-zlib-dir={$builder->getBuildRootPath()}";
        return "--with-zlib{$zlib_dir}";
    }
}
