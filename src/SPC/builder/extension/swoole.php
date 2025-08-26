<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

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

        // commonly used feature: coroutine-time
        $arg .= ' --enable-swoole-coro-time --with-pic';

        $arg .= $this->builder->getOption('enable-zts') ? ' --enable-swoole-thread --disable-thread-context' : ' --disable-swoole-thread --enable-thread-context';

        // required features: curl, openssl (but curl hook is buggy for php 8.0)
        $arg .= $this->builder->getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        $arg .= ' --enable-openssl';

        // additional features that only require libraries
        $arg .= $this->builder->getLib('libcares') ? ' --enable-cares' : '';
        $arg .= $this->builder->getLib('brotli') ? (' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $this->builder->getLib('nghttp2') ? (' --with-nghttp2-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $this->builder->getLib('zstd') ? ' --enable-zstd' : '';
        $arg .= $this->builder->getLib('liburing') ? ' --enable-iouring' : '';
        $arg .= $this->builder->getExt('sockets') ? ' --enable-sockets' : '';

        // enable additional features that require the pdo extension, but conflict with pdo_* extensions
        // to make sure everything works as it should, this is done in fake addon extensions
        $arg .= $this->builder->getExt('swoole-hook-pgsql') ? ' --enable-swoole-pgsql' : ' --disable-swoole-pgsql';
        $arg .= $this->builder->getExt('swoole-hook-mysql') ? ' --enable-mysqlnd' : ' --disable-mysqlnd';
        $arg .= $this->builder->getExt('swoole-hook-sqlite') ? ' --enable-swoole-sqlite' : ' --disable-swoole-sqlite';

        if ($this->builder->getExt('swoole-hook-odbc')) {
            $config = (new SPCConfigUtil($this->builder, ['libs_only_deps' => true]))->config([], ['unixodbc']);
            $arg .= ' --with-swoole-odbc=unixODBC,' . BUILD_ROOT_PATH . ' SWOOLE_ODBC_LIBS="' . $config['libs'] . '"';
        }

        if (SPCTarget::getTargetOS() === 'Darwin') {
            $arg .= ' ac_cv_lib_pthread_pthread_barrier_init=no';
        }

        return $arg;
    }
}
