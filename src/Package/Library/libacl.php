<?php

declare(strict_types=1);

namespace Package\Library;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libacl')]
class libacl
{
    #[BeforeStage('php', [php::class, 'makeForUnix'], 'libacl')]
    public function patchBeforeMakePhpUnix(LibraryPackage $lib): void
    {
        $file_path = SOURCE_PATH . '/php-src/Makefile';
        $file_content = FileSystem::readFile($file_path);
        if (!preg_match('/FPM_EXTRA_LIBS =(.*)-lacl/', $file_content)) {
            return;
        }
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/Makefile', '/FPM_EXTRA_LIBS =(.*)-lacl ?(.*)/', 'FPM_EXTRA_LIBS =$1$2');
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->configure('--disable-nls', '--disable-tests')
            ->make('install-acl_h install-libacl_h install-data install-libLTLIBRARIES install-pkgincludeHEADERS install-sysincludeHEADERS install-pkgconfDATA', with_install: false);
        $lib->patchPkgconfPrefix(['libacl.pc'], PKGCONF_PATCH_PREFIX);
    }
}
