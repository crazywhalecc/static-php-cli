<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait readline
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--with-curses',
                '--enable-multibyte=yes',
            )
            ->make();
        $this->patchPkgconfPrefix(['readline.pc']);
    }
}
