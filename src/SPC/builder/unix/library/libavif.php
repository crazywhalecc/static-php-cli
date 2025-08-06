<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libavif
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DAVIF_LIBYUV=OFF')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libavif.pc']);
    }
}
