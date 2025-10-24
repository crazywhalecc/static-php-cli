<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait libacl
{
    public function patchBeforeMake(): bool
    {
        $file_path = SOURCE_PATH . '/php-src/Makefile';
        $file_content = FileSystem::readFile($file_path);
        if (!preg_match('/FPM_EXTRA_LIBS =(.*)-lacl/', $file_content)) {
            return false;
        }
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/Makefile', '/FPM_EXTRA_LIBS =(.*)-lacl ?(.*)/', 'FPM_EXTRA_LIBS =$1$2');
        return true;
    }

    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->configure('--disable-nls', '--disable-tests')
            ->make('install-acl_h install-libacl_h install-data install-libLTLIBRARIES install-pkgincludeHEADERS install-sysincludeHEADERS install-pkgconfDATA', with_install: false);
        $this->patchPkgconfPrefix(['libacl.pc'], PKGCONF_PATCH_PREFIX);
    }
}
