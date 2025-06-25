<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait attr
{
    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->exec('libtoolize --force --copy')
            ->exec('./autogen.sh || autoreconf -if')
            ->configure('--disable-nls')
            ->make();
        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
