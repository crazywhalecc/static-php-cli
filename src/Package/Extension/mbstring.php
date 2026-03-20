<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;

#[Extension('mbstring')]
class mbstring
{
    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getUnixConfigureArg(bool $shared, PackageInstaller $installer): string
    {
        $arg = '--enable-mbstring' . ($shared ? '=shared' : '');
        $arg .= $installer->isPackageResolved('ext-mbregex') === false ? ' --disable-mbregex' : ' --enable-mbregex';
        return $arg;
    }
}
