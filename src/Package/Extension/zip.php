<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('zip')]
class zip extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared): string
    {
        return !$shared ? ('--with-zip=' . BUILD_ROOT_PATH) : '--enable-zip=shared';
    }
}
