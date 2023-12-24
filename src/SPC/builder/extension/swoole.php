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
        $arg = ' --enable-swoole --enable-sockets --enable-mysqlnd  --enable-cares ';
        $arg .= ' --enable-swoole-coro-time --enable-thread-context';
        $arg .= ' --with-brotli-dir=' . BUILD_ROOT_PATH;
        $arg .= ' --with-nghttp2-dir=' . BUILD_ROOT_PATH;

        $arg .= $this->builder->getLib('postgresql') ? ' --enable-swoole-pgsql ' : ' ';

        // enable this feature , need remove pdo_sqlite
        // more info : https://wenda.swoole.com/detail/109023
        // $arg .= $this->builder->getLib('sqlite') ? ' --enable-swoole-sqlite ' : ' ';

        // enable this feature , need stop pdo_*
        // $arg .= $this->builder->getLib('unixodbc') ? ' --with-swoole-odbc=unixODBC,'  : ' ';

        // swoole curl hook is buggy for php 8.0
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';

        return $arg;
    }
}
