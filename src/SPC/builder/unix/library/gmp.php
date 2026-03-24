<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait gmp
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => '-std=c17',
            ])
            ->configure(
                '--enable-fat'
            )
            ->make();
        $this->patchPkgconfPrefix(['gmp.pc']);
    }
}
