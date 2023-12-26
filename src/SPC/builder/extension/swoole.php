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
        // enable swoole
        $arg = '--enable-swoole';

        // commonly-used feature: coroutine-time, thread-context
        $arg .= ' --enable-swoole-coro-time --enable-thread-context';

        // required feature: curl, openssl (but curl hook is buggy for php 8.0)
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        $arg .= ' --enable-openssl';

        // additional feature: c-ares, brotli, nghttp2 (can be disabled, but we enable it by default in config)
        $arg .= $this->builder->getLib('libcares') ? ' --enable-cares' : '';
        $arg .= $this->builder->getLib('brotli') ? (' --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $this->builder->getLib('nghttp2') ? (' --with-nghttp2-dir=' . BUILD_ROOT_PATH) : '';

        // additional feature: pgsql
        $arg .= $this->builder->getExt('pgsql') ? ' --enable-swoole-pgsql ' : '';

        // enable this feature , need remove pdo_sqlite
        // more info : https://wenda.swoole.com/detail/109023
        // $arg .= $this->builder->getLib('sqlite') ? ' --enable-swoole-sqlite ' : ' ';

        // enable this feature , need stop pdo_*
        // $arg .= $this->builder->getLib('unixodbc') ? ' --with-swoole-odbc=unixODBC,'  : ' ';
        return $arg;
    }
}
