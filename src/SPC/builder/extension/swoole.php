<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('swoole')]
class swoole extends Extension
{
    public function patchBeforeMake(): bool
    {
        $patched = parent::patchBeforeMake();
        if ($this->builder instanceof MacOSBuilder) {
            // Fix swoole with event extension <util.h> conflict bug
            $util_path = shell()->execWithResult('xcrun --show-sdk-path', false)[1][0] . '/usr/include/util.h';
            FileSystem::replaceFileStr(
                "{$this->source_dir}/thirdparty/php/standard/proc_open.cc",
                'include <util.h>',
                'include "' . $util_path . '"',
            );
            return true;
        }
        return $patched;
    }

    public function getExtVersion(): ?string
    {
        // Get version from source directory
        $file = SOURCE_PATH . '/php-src/ext/swoole/include/swoole_version.h';
        // Match #define SWOOLE_VERSION "5.1.3"
        $pattern = '/#define SWOOLE_VERSION "(.+)"/';
        if (preg_match($pattern, file_get_contents($file), $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        // enable swoole
        $arg = '--enable-swoole' . ($shared ? '=shared' : '');

        // commonly-used feature: coroutine-time
        $arg .= ' --enable-swoole-coro-time --with-pic';

        $arg .= $this->builder->getOption('enable-zts') ? ' --enable-swoole-thread --disable-thread-context' : ' --disable-swoole-thread --enable-thread-context';

        // required feature: curl, openssl (but curl hook is buggy for php 8.0)
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        $arg .= ' --enable-openssl';

        // additional feature: c-ares, brotli, nghttp2 (can be disabled, but we enable it by default in config to support full network feature)
        $arg .= $this->builder->getLib('libcares') ? ' --enable-cares' : '';
        if (!$shared) {
            $arg .= $this->builder->getLib('brotli') ? (' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        }
        $arg .= $this->builder->getLib('nghttp2') ? (' --with-nghttp2-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $this->builder->getLib('zstd') ? ' --enable-zstd' : '';
        $arg .= $this->builder->getLib('liburing') ? ' --enable-iouring' : '';
        $arg .= $this->builder->getExt('sockets') ? ' --enable-sockets' : '';

        // additional feature: swoole-pgsql, it should depend on lib [postgresql], but it will lack of CFLAGS etc.
        // so this is a tricky way (enable ext [pgsql,pdo] to add postgresql hook and pdo_pgsql support)
        $arg .= $this->builder->getExt('swoole-hook-pgsql') ? ' --enable-swoole-pgsql' : ' --disable-swoole-pgsql';

        // additional feature: swoole-mysql
        $arg .= $this->builder->getExt('swoole-hook-mysql') ? ' --enable-mysqlnd' : ' --disable-mysqlnd';

        // enable this feature , need remove pdo_sqlite
        // more info : https://wenda.swoole.com/detail/109023
        $arg .= $this->builder->getExt('swoole-hook-sqlite') ? ' --enable-swoole-sqlite' : ' --disable-swoole-sqlite';

        // enable this feature , need stop pdo_*
        $arg .= $this->builder->getLib('unixodbc') && !$this->builder->getExt('pdo')?->isBuildStatic() ? ' --with-swoole-odbc=unixODBC,' . BUILD_ROOT_PATH : '';
        return $arg;
    }
}
