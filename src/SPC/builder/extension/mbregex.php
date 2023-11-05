<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mbregex')]
class mbregex extends Extension
{
    public function getDistName(): string
    {
        return 'mbstring';
    }

    public function getConfigureArg(): string
    {
        return '';
    }
}
