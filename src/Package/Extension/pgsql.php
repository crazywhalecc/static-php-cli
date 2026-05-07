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

#[Extension('pgsql')]
class pgsql extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder, PackageInstaller $installer): string
    {
        if (php::getPHPVersionID() >= 80400) {
            $libfiles = new SPCConfigUtil(['libs_only_deps' => true, 'absolute_libs' => true])->getPackageDepsConfig('postgresql', array_keys($installer->getResolvedPackages()), $builder->getOption('with-suggests'))['libs'];
            $libfiles = str_replace("{$builder->getLibDir()}/lib", '-l', $libfiles);
            $libfiles = str_replace('.a', '', $libfiles);
            return '--with-pgsql' . ($shared ? '=shared' : '') .
                ' PGSQL_CFLAGS=-I' . $builder->getIncludeDir() .
                ' PGSQL_LIBS="-L' . $builder->getLibDir() . ' ' . $libfiles . '"';
        }
        return '--with-pgsql=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath();
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        if (php::getPHPVersionID() >= 80400) {
            return '--with-pgsql';
        }
        return "--with-pgsql={$builder->getBuildRootPath()}";
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=c17 -Wno-int-conversion';
        return $parent;
    }
}
