<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait libmpdec
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure('--disable-cxx --disable-shared --enable-static')
            ->make();
    }
}
