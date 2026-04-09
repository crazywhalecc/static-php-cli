<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('sqlsrv')]
class sqlsrv extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-sqlsrv')]
    #[PatchDescription('Remove /sdl /W4 /WX flags from sqlsrv config.w32 to prevent strict compilation failures on Windows (these flags get merged into STATIC_EXT_CFLAGS and applied to Zend engine files)')]
    public function patchBeforeBuildconfForWindows(): void
    {
        // Fix the compilation issue of sqlsrv on Windows (/sdl causes C4703 to be treated as errors in Zend files)
        if (file_exists(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32')) {
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32', '/sdl /W4 /WX', '');
        }
    }

    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-sqlsrv')]
    #[PatchDescription('Fix sqlsrv Makefile: remove /sdl /W4 /WX flags to prevent build errors on Windows')]
    public function patchBeforeMake(PackageInstaller $installer): bool
    {
        $makefile = $installer->getTargetPackage('php')->getSourceDir() . '\Makefile';
        $makeContent = file_get_contents($makefile);
        $makeContent = preg_replace('/^(CFLAGS_(?:PDO_)?SQLSRV=.*?)\s+\/sdl\b/m', '$1', $makeContent);
        $makeContent = preg_replace('/^(CFLAGS_(?:PDO_)?SQLSRV=.*?)\s+\/W4\b/m', '$1', $makeContent);
        $makeContent = preg_replace('/^(CFLAGS_(?:PDO_)?SQLSRV=.*?)\s+\/WX\b/m', '$1', $makeContent);
        file_put_contents($makefile, $makeContent);
        return true;
    }
}
