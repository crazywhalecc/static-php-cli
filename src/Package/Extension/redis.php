<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('redis')]
class redis extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer, PackageBuilder $builder): string
    {
        $arg = '--enable-redis';
        if ($this->isBuildStatic()) {
            $arg .= $installer->getPhpExtensionPackage('session')?->isBuildStatic() ? ' --enable-redis-session' : ' --disable-redis-session';
            $arg .= $installer->getPhpExtensionPackage('igbinary')?->isBuildStatic() ? ' --enable-redis-igbinary' : ' --disable-redis-igbinary';
            $arg .= $installer->getPhpExtensionPackage('msgpack')?->isBuildStatic() ? ' --enable-redis-msgpack' : ' --disable-redis-msgpack';
        } else {
            $arg .= $installer->getPhpExtensionPackage('session') ? ' --enable-redis-session' : ' --disable-redis-session';
            $arg .= $installer->getPhpExtensionPackage('igbinary') ? ' --enable-redis-igbinary' : ' --disable-redis-igbinary';
            $arg .= $installer->getPhpExtensionPackage('msgpack') ? ' --enable-redis-msgpack' : ' --disable-redis-msgpack';
        }
        if ($zstd = $installer->getLibraryPackage('zstd')) {
            $arg .= ' --enable-redis-zstd --with-libzstd="' . $zstd->getBuildRootPath() . '"';
        }
        if ($liblz4 = $installer->getLibraryPackage('liblz4')) {
            $arg .= ' --enable-redis-lz4 --with-liblz4="' . $liblz4->getBuildRootPath() . '"';
        }
        return $arg;
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-redis';
        $arg .= $installer->getPhpExtensionPackage('session') ? ' --enable-redis-session' : ' --disable-redis-session';
        $arg .= $installer->getPhpExtensionPackage('igbinary') ? ' --enable-redis-igbinary' : ' --disable-redis-igbinary';
        return $arg;
    }
}
