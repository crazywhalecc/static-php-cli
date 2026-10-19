<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

class pdo_sqlsrv
{
    #[AfterSourceExtract('ext-pdo_sqlsrv')]
    #[PatchDescription('Fix pdo_sqlsrv directory structure for PHP 8.5+ (source layout changed)')]
    public function patchDirectoryStructureForPhp85(string $target_path): void
    {
        if (!file_exists($target_path . '/config.m4') && is_dir($target_path . '/source/pdo_sqlsrv')) {
            FileSystem::moveFileOrDir($target_path . '/LICENSE', $target_path . '/source/pdo_sqlsrv/LICENSE');
            FileSystem::moveFileOrDir($target_path . '/source/shared', $target_path . '/source/pdo_sqlsrv/shared');
            FileSystem::moveFileOrDir($target_path . '/source/pdo_sqlsrv', SOURCE_PATH . '/pdo_sqlsrv');
            FileSystem::removeDir($target_path);
            FileSystem::moveFileOrDir(SOURCE_PATH . '/pdo_sqlsrv', $target_path);
        }
    }
}
