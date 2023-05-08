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
        $arg = '--enable-mongodb --without-mongodb-sasl';
        if ($this->builder->getLib('openssl')) {
            $arg .= '--with-mongodb-system-libs=no --with-mongodb-ssl=openssl';
        } else {
            // 禁用，否则链接的是系统库
            $arg .= '';
        }
        if ($this->builder->getLib('icu')) {
            $arg .= '--with-mongodb-system-libs=no --with-mongodb-ssl=openssl';
        } else {
            // 禁用，否则链接的是系统库
            $arg .= '';
        }
        return $arg;
    }
}
