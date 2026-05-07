<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('gd')]
class gd extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-gd' . ($shared ? '=shared' : '');
        $arg .= $installer->getLibraryPackage('freetype') ? ' --with-freetype' : '';
        $arg .= $installer->getLibraryPackage('libjpeg') ? ' --with-jpeg' : '';
        $arg .= $installer->getLibraryPackage('libwebp') ? ' --with-webp' : '';
        $arg .= $installer->getLibraryPackage('libavif') ? ' --with-avif' : '';
        return $arg;
    }
}
