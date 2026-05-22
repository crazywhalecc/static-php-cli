<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('clickhouse')]
class clickhouse extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-clickhouse' . ($shared ? '=shared' : '');
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= ' --enable-clickhouse-openssl';
        }
        return $arg;
    }
}
