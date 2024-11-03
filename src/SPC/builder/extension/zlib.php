<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('zlib')]
class zlib extends Extension
{
    public function getUnixConfigureArg(): string
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            return '--with-zlib';
        }
        return '--with-zlib --with-zlib-dir="' . BUILD_ROOT_PATH . '"';
    }
}
