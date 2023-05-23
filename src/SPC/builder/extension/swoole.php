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
            $arg .= ' --enable-openssl --with-openssl-dir=' . BUILD_ROOT_PATH;
        }
        if ($this->builder->getLib('brotli')) {
            $arg .= ' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH;
        }
        if ($this->builder->getLib('curl')) {
            $arg .= ' --enable-swoole-curl ';
            // curl 启用 nghttp2 与 swoole 内置的nghttp2 不能同时启用；因为编译时会出现 同一个函数多重定义
            if ($this->builder->getLib('nghttp2')) {
                $arg .= ' --with-nghttp2-dir=' . BUILD_ROOT_PATH;
            }
        }
        // $arg .= $this->builder->getLib('openssl') ? ' --enable-openssl' : ' --disable-openssl --without-openssl';
        // $arg .= $this->builder->getLib('brotli') ? (' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        // curl hook is buggy for static php
        // $arg .= ' --disable-swoole-curl';
        return $arg;
    }
}
