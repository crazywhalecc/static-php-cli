<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait libunistring
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure('--disable-nls')
            ->make();
        $this->patchLaDependencyPrefix();
    }
}
