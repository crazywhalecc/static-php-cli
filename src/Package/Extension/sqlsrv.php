<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('sqlsrv')]
class sqlsrv extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-sqlsrv')]
    #[PatchDescription('Fix sqlsrv Makefile: remove /W4 and /WX flags to prevent build errors on Windows')]
    public function patchBeforeMake(PackageInstaller $installer): bool
    {
        $makefile = $installer->getTargetPackage('php')->getSourceDir() . '\Makefile';
        $makeContent = file_get_contents($makefile);
        $makeContent = preg_replace('/^(CFLAGS_(?:PDO_)?SQLSRV=.*?)\s+\/W4\b/m', '$1', $makeContent);
        $makeContent = preg_replace('/^(CFLAGS_(?:PDO_)?SQLSRV=.*?)\s+\/WX\b/m', '$1', $makeContent);
        file_put_contents($makefile, $makeContent);
        return true;
    }
}
