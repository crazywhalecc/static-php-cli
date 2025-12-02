<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pdo_sqlsrv')]
class pdo_sqlsrv extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!file_exists($this->source_dir . '/config.m4') && is_dir($this->source_dir . '/source/pdo_sqlsrv')) {
            FileSystem::moveFileOrDir($this->source_dir . '/LICENSE', $this->source_dir . '/source/pdo_sqlsrv/LICENSE');
            FileSystem::moveFileOrDir($this->source_dir . '/source/shared', $this->source_dir . '/source/pdo_sqlsrv/shared');
            FileSystem::moveFileOrDir($this->source_dir . '/source/pdo_sqlsrv', SOURCE_PATH . '/pdo_sqlsrv');
            FileSystem::removeDir($this->source_dir);
            FileSystem::moveFileOrDir(SOURCE_PATH . '/pdo_sqlsrv', $this->source_dir);
            return true;
        }
        return false;
    }
}
