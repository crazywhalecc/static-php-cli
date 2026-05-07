<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

#[Extension('pdo_sqlsrv')]
class pdo_sqlsrv
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-pdo_sqlsrv')]
    #[PatchDescription('Remove /sdl flag from pdo_sqlsrv config.w32 to prevent strict SDL check compilation failures')]
    public function patchBeforeBuildconfForWindows(): void
    {
        // Fix the compilation issue of pdo_sqlsrv on Windows (/sdl check is too strict and will cause Zend compilation to fail)
        if (file_exists(SOURCE_PATH . '/php-src/ext/pdo_sqlsrv/config.w32')) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/pdo_sqlsrv/config.w32', '/sdl', '');
        }
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-pdo_sqlsrv')]
    #[PatchDescription('Fix pdo_sqlsrv directory structure for PHP 8.5+ (source layout changed)')]
    public function patchDirectoryStructureForPhp85(): void
    {
        $source_dir = SOURCE_PATH . '/php-src/ext/pdo_sqlsrv';
        if (!file_exists($source_dir . '/config.m4') && is_dir($source_dir . '/source/pdo_sqlsrv')) {
            FileSystem::moveFileOrDir($source_dir . '/LICENSE', $source_dir . '/source/pdo_sqlsrv/LICENSE');
            FileSystem::moveFileOrDir($source_dir . '/source/shared', $source_dir . '/source/pdo_sqlsrv/shared');
            FileSystem::moveFileOrDir($source_dir . '/source/pdo_sqlsrv', SOURCE_PATH . '/pdo_sqlsrv');
            FileSystem::removeDir($source_dir);
            FileSystem::moveFileOrDir(SOURCE_PATH . '/pdo_sqlsrv', $source_dir);
        }
    }
}
