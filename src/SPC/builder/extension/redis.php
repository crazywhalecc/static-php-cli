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
        if (!$this->builder->getExt('session')) {
            $arg .= ' --disable-redis-session';
        } else {
            $arg .= ' --enable-redis-session';
        }
        if ($this->builder->getLib('zstd')) {
            $arg .= ' --enable-redis-zstd --with-libzstd="' . BUILD_ROOT_PATH . '"';
        }
        return $arg;
    }
}
