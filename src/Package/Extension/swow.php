<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('swow')]
class swow extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function configureArg(PackageInstaller $installer): string
    {
        $arg = '--enable-swow';
        $arg .= $installer->getLibraryPackage('openssl') ? ' --enable-swow-ssl' : ' --disable-swow-ssl';
        $arg .= $installer->getLibraryPackage('curl') ? ' --enable-swow-curl' : ' --disable-swow-curl';
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-swow')]
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-swow')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        if (php::getPHPVersionID() >= 80000 && !is_link("{$php_src}/ext/swow")) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru("cd {$php_src}/ext && mklink /D swow swow-src\\ext");
            } else {
                f_passthru("cd {$php_src}/ext && ln -s swow-src/ext swow");
            }
        }
        // replace AC_DEFUN([SWOW_PKG_CHECK_MODULES] to AC_DEFUN([SWOW_PKG_CHECK_MODULES_STATIC]
        FileSystem::replaceFileStr($this->getSourceDir() . '/ext/config.m4', 'AC_DEFUN([SWOW_PKG_CHECK_MODULES]', 'AC_DEFUN([SWOW_PKG_CHECK_MODULES_STATIC]');
        return false;
    }
}
