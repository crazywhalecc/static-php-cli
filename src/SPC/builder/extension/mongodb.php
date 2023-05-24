<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mongodb')]
class mongodb extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = ' --enable-mongodb ';
        $arg .= ' --with-mongodb-system-libs=no ';
        $arg .= ' --with-mongodb-sasl=no  ';
        if ($this->builder->getLib('openssl')) {
            $arg .= '--with-mongodb-ssl=openssl';
        }
        if ($this->builder->getLib('icu')) {
            $arg .= ' --with-mongodb-icu=yes ';
        } else {
            $arg .= ' --with-mongodb-icu=no ';
        }
        return $arg;
    }
}
