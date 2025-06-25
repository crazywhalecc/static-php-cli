<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait sqlite
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure()->make();
        $this->patchPkgconfPrefix(['sqlite3.pc']);
    }
}
