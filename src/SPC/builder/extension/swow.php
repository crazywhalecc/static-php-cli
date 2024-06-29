<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('swow')]
class swow extends Extension
{
    public function validate(): void
    {
        if ($this->builder->getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new RuntimeException('The latest swow extension requires PHP 8.0 or later');
        }
    }

    public function getConfigureArg(): string
    {
        $arg = '--enable-swow';
        $arg .= $this->builder->getLib('openssl') ? ' --enable-swow-ssl' : ' --disable-swow-ssl';
        $arg .= $this->builder->getLib('curl') ? ' --enable-swow-curl' : ' --disable-swow-curl';
        return $arg;
    }

    /**
     * @throws RuntimeException
     */
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder->getPHPVersionID() >= 80000 && !is_link(SOURCE_PATH . '/php-src/ext/swow')) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D swow swow-src\ext');
            } else {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s swow-src/ext swow');
            }
            return true;
        }
        return false;
    }
}
