<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('sqlsrv')]
class sqlsrv extends Extension
{
    private bool $pdo_sqlsrv_patched = false;

    public function patchBeforeBuildconf(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' && $this->builder->getExt('pdo_sqlsrv') === null) {
            // support sqlsrv without pdo_sqlsrv
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32', 'PHP_PDO_SQLSRV', '"no"');
            $this->pdo_sqlsrv_patched = true;
            return true;
        }
        return false;
    }

    public function patchBeforeConfigure(): bool
    {
        if ($this->pdo_sqlsrv_patched) {
            // revert pdo_sqlsrv patch
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32', '"no" == "no"', 'PHP_PDO_SQLSRV == "no"');
            return true;
        }
        return false;
    }
}
