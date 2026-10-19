<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('http')]
class http extends PhpExtensionPackage
{
    private const array SUGGESTED_LIBS = [
        'libevent' => 'libevent',
        'libicu' => 'icu',
        'libidn2' => 'idn2',
    ];

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer, PackageBuilder $builder): string
    {
        $root = escapeshellarg($builder->getBuildRootPath());
        $arg = '--with-http' . ($shared ? '=shared' : '') . " --with-http-zlib-dir={$root} --with-http-libbrotli-dir={$root} --with-http-libcurl-dir={$root}";
        foreach (self::SUGGESTED_LIBS as $option => $lib) {
            $arg .= $installer->getLibraryPackage($lib) === null ? " --without-http-{$option}-dir" : " --with-http-{$option}-dir={$root}";
        }
        return $arg . ' --without-http-libidn-dir --without-http-libidnkit-dir --without-http-libidnkit2-dir';
    }
}
