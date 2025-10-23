<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait attr
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => '-Wno-int-conversion -Wno-implicit-function-declaration',
            ])
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->configure('--disable-nls')
            ->make('install-attributes_h install-data install-libattr_h install-libLTLIBRARIES install-pkgincludeHEADERS install-pkgconfDATA', with_install: false);
        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
