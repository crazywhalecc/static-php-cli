<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait nghttp3
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure('--enable-lib-only')->make();
        $this->patchPkgconfPrefix(['libnghttp3.pc']);
        $this->patchLaDependencyPrefix();
    }
}
