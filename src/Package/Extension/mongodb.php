<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('mongodb')]
class mongodb extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = ' --enable-mongodb' . ($shared ? '=shared' : '') . ' ';
        $arg .= ' --with-mongodb-system-libs=no --with-mongodb-client-side-encryption=no ';
        $arg .= ' --with-mongodb-sasl=no ';
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= '--with-mongodb-ssl=openssl';
        }
        $arg .= $installer->getLibraryPackage('icu') ? ' --with-mongodb-icu=yes ' : ' --with-mongodb-icu=no ';
        $arg .= $installer->getLibraryPackage('zstd') ? ' --with-mongodb-zstd=yes ' : ' --with-mongodb-zstd=no ';
        // $arg .= $installer->getLibraryPackage('snappy') ? ' --with-mongodb-snappy=yes ' : ' --with-mongodb-snappy=no ';
        $arg .= $installer->getLibraryPackage('zlib') ? ' --with-mongodb-zlib=yes ' : ' --with-mongodb-zlib=bundled ';
        return clean_spaces($arg);
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=c17';
        return $parent;
    }
}
