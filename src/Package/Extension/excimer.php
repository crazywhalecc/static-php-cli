<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('excimer')]
class excimer extends PhpExtensionPackage
{
    public function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        $env['LIBS'] = clean_spaces(str_replace('-lphp', '', $env['LIBS']));
        return $env;
    }
}
