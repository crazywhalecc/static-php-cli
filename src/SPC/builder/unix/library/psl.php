<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait psl
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('idn2', ...ac_with_args('libidn2', true))
            ->configure('--disable-nls')
            ->make();
        $this->patchPkgconfPrefix(['libpsl.pc']);
        $this->patchLaDependencyPrefix();
    }
}
