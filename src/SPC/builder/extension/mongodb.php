<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('mongodb')]
class mongodb extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = ' --enable-mongodb' . ($shared ? '=shared' : '') . ' ';
        $arg .= ' --with-mongodb-system-libs=no --with-mongodb-client-side-encryption=no ';
        $arg .= ' --with-mongodb-sasl=no ';
        if ($this->builder->getLib('openssl')) {
            $arg .= '--with-mongodb-ssl=openssl';
        }
        $arg .= $this->builder->getLib('icu') ? ' --with-mongodb-icu=yes ' : ' --with-mongodb-icu=no ';
        $arg .= $this->builder->getLib('zstd') ? ' --with-mongodb-zstd=yes ' : ' --with-mongodb-zstd=no ';
        // $arg .= $this->builder->getLib('snappy') ? ' --with-mongodb-snappy=yes ' : ' --with-mongodb-snappy=no ';
        $arg .= $this->builder->getLib('zlib') ? ' --with-mongodb-zlib=yes ' : ' --with-mongodb-zlib=bundled ';
        return clean_spaces($arg);
    }
}
