<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait libiconv
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure('--enable-extra-encodings')->make();
        $this->patchLaDependencyPrefix();
    }
}
