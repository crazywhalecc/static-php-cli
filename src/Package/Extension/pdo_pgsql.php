<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\SPCConfigUtil;

#[Extension('pdo_pgsql')]
class pdo_pgsql extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder, PackageInstaller $installer): string
    {
        if (php::getPHPVersionID() >= 80400) {
            // These override pkg-config, so they must carry libpq itself too
            $libs = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true])->configForResolvedBuild(['postgresql'], $installer)['libs'];
            return '--with-pdo-pgsql' . ($shared ? '=shared' : '') .
                ' PGSQL_CFLAGS=-I' . $builder->getIncludeDir() .
                ' PGSQL_LIBS="-L' . $builder->getLibDir() . ' ' . $libs . '"';
        }
        return '--with-pdo-pgsql=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath();
    }
}
