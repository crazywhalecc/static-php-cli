<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;

#[Extension('mbregex')]
class mbregex
{
    #[CustomPhpConfigureArg('Linux')]
    #[CustomPhpConfigureArg('Darwin')]
    public function getUnixConfigureArg(): string
    {
        return '';
    }
}
