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
        // pgsql hook is buggy for static php
        $arg .= ' --disable-swoole-pgsql';
        $arg .= $this->builder->getLib('openssl') ? ' --enable-openssl' : ' --disable-openssl --without-openssl';
        $arg .= $this->builder->getLib('brotli') ? (' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH) : ' --disable-brotli';
        // swoole curl hook is buggy for php 8.0
        $arg .= $this->builder->getExt('curl') && $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        return $arg;
    }
}
