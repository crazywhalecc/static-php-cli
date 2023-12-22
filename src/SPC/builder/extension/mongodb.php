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
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/mongodb/config.m4', 'if test -z "$PHP_CONFIG"; then', 'if false; then');
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/mongodb/config.m4', 'PHP_MONGODB_PHP_VERSION=`${PHP_CONFIG} --version`', 'PHP_MONGODB_PHP_VERSION=' . $this->builder->getPHPVersion());
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/mongodb/config.m4', 'PHP_MONGODB_PHP_VERSION_ID=`${PHP_CONFIG} --vernum`', 'PHP_MONGODB_PHP_VERSION_ID=' . $this->builder->getPHPVersionID());
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        $arg = ' --enable-mongodb ';
        $arg .= ' --with-mongodb-system-libs=no --with-mongodb-client-side-encryption=no ';
        $arg .= ' --with-mongodb-sasl=no  ';
        if ($this->builder->getLib('openssl')) {
            $arg .= '--with-mongodb-ssl=openssl';
        }
        $arg .= $this->builder->getLib('icu') ? ' --with-mongodb-icu=yes ' : ' --with-mongodb-icu=no ';
        $arg .= $this->builder->getLib('zstd') ? ' --with-mongodb-zstd=yes ' : ' --with-mongodb-zstd=no ';
        // $arg .= $this->builder->getLib('snappy') ? ' --with-mongodb-snappy=yes ' : ' --with-mongodb-snappy=no ';
        $arg .= $this->builder->getLib('zlib') ? ' --with-mongodb-zlib=yes ' : ' --with-mongodb-zlib=bundled ';
        return $arg;
    }
}
