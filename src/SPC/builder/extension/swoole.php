<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('swoole')]
class swoole extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-swoole';
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --enable-openssl';
        } else {
            $arg .= ' --disable-openssl --without-openssl';
        }
        // curl hook is buggy for static php
        $arg .= ' --disable-swoole-curl';
        return $arg;
    }
}
