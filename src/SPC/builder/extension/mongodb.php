<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('mongodb')]
class mongodb extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/ext/mongodb/config.m4',
            '/^(\s+)(src\/libmongoc\/)/m',
            '$1${ac_config_dir}/$2'
        );
        return true;
    }

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

    public function getExtraEnv(): array
    {
        return ['CFLAGS' => '-std=c17'];
    }
}
