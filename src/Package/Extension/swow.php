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
    #[CustomPhpConfigureArg('Windows')]
    public function configureArg(PackageInstaller $installer): string
    {
        $arg = '--enable-swow --disable-swow-pdo-pgsql';
        $arg .= $installer->getLibraryPackage('openssl') ? ' --enable-swow-ssl' : ' --disable-swow-ssl';
        $arg .= $installer->getLibraryPackage('curl') ? ' --enable-swow-curl' : ' --disable-swow-curl';
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-swow')]
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-swow')]
    public function patchBeforeBuildconf(): bool
    {
        // replace AC_DEFUN([SWOW_PKG_CHECK_MODULES] to AC_DEFUN([SWOW_PKG_CHECK_MODULES_STATIC]
        FileSystem::replaceFileStr($this->getBuildDir() . '/config.m4', 'AC_DEFUN([SWOW_PKG_CHECK_MODULES]', 'AC_DEFUN([SWOW_PKG_CHECK_MODULES_STATIC]');
        return true;
    }
}
