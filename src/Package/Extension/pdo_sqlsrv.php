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
}
