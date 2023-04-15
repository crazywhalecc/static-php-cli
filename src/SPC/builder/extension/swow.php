<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('swow')]
class swow extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-swow';
        $arg .= $this->builder->getLib('openssl') ? ' --enable-swow-ssl' : ' --disable-swow-ssl';
        $arg .= $this->builder->getLib('curl') ? ' --enable-swow-curl' : ' --disable-swow-curl';
        return $arg;
    }
}
