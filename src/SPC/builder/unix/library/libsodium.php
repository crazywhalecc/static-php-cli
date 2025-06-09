<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait libsodium
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure()->make();
        $this->patchPkgconfPrefix(['libsodium.pc'], PKGCONF_PATCH_PREFIX);
    }
}
