<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait libedit
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-D__STDC_ISO_10646__=201103L'])
            ->configure()
            ->make();
        $this->patchPkgconfPrefix(['libedit.pc']);
    }
}
