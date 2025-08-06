<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libde265
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DENABLE_SDL=OFF')
            ->build();
        $this->patchPkgconfPrefix(['libde265.pc']);
    }
}
