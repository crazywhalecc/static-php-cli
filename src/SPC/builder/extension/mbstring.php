<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mbstring')]
class mbstring extends Extension
{
    public function getConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-mbstring' . ($shared ? '=shared' : '');
        if ($this->builder->getExt('mbregex') === null) {
            $arg .= ' --disable-mbregex';
        } else {
            $arg .= ' --enable-mbregex';
        }
        return $arg;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-mbstring' . ($shared ? '=shared' : '');
        if ($this->builder->getExt('mbregex') === null) {
            $arg .= ' --disable-mbregex';
        } else {
            $arg .= ' --enable-mbregex';
        }
        return $arg;
    }
}
