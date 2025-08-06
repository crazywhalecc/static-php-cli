<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libjpeg
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DENABLE_STATIC=ON',
                '-DENABLE_SHARED=OFF',
            )
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libjpeg.pc', 'libturbojpeg.pc']);
    }
}
