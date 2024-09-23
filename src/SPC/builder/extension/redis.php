<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('redis')]
class redis extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-redis';
        $arg .= $this->builder->getExt('session') ? ' --enable-redis-session' : ' --disable-redis-session';
        $arg .= $this->builder->getExt('igbinary') ? ' --enable-redis-igbinary' : ' --disable-redis-igbinary';
        if ($this->builder->getLib('zstd')) {
            $arg .= ' --enable-redis-zstd --with-libzstd="' . BUILD_ROOT_PATH . '"';
        }
        if ($this->builder->getLib('liblz4')) {
            $arg .= ' --enable-redis-lz4 --with-liblz4="' . BUILD_ROOT_PATH . '"';
        }
        return $arg;
    }

    public function getWindowsConfigureArg(): string
    {
        $arg = '--enable-redis';
        $arg .= $this->builder->getExt('session') ? ' --enable-redis-session' : ' --disable-redis-session';
        $arg .= $this->builder->getExt('igbinary') ? ' --enable-redis-igbinary' : ' --disable-redis-igbinary';
        return $arg;
    }
}
