<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mbstring')]
class mbstring extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-mbstring';
        if ($this->builder->getExt('mbregex') === null) {
            $arg .= ' --disable-mbregex';
        }
        return $arg;
    }
}
