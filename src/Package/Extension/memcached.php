<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('memcached')]
class memcached extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        return '--enable-memcached' . ($shared ? '=shared' : '') . ' ' .
            '--with-zlib-dir=' . $installer->getLibraryPackage('zlib')->getBuildRootPath() . ' ' .
            '--with-libmemcached-dir=' . $installer->getLibraryPackage('libmemcached')->getBuildRootPath() . ' ' .
            '--disable-memcached-sasl ' .
            '--enable-memcached-json ' .
            ($installer->getLibraryPackage('zstd') ? '--with-zstd ' : '') .
            ($installer->getPhpExtensionPackage('ext-igbinary') ? '--enable-memcached-igbinary ' : '') .
            ($installer->getPhpExtensionPackage('ext-session') ? '--enable-memcached-session ' : '') .
            ($installer->getPhpExtensionPackage('ext-msgpack') ? '--enable-memcached-msgpack ' : '') .
            '--with-system-fastlz';
    }
}
