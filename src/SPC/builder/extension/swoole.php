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
        $arg = ' --enable-pdo --enable-sockets --enable-mysqlnd --enable-phar --enable-session ';
        $arg .= ' --enable-swoole --enable-sockets --enable-mysqlnd  --enable-cares ';
        $arg .= ' --with-brotli-dir=' . BUILD_ROOT_PATH;
        $arg .= ' --with-nghttp2-dir=' . BUILD_ROOT_PATH;

        $arg .= $this->builder->getLib('postgresql') ? ' --enable-swoole-pgsql' : ' ';
        // swoole curl hook is buggy for php 8.0
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';

        return $arg;
    }
}
