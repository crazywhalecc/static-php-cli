<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait mimalloc
{
    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DMI_BUILD_SHARED=OFF',
                '-DMI_BUILD_OBJECT=OFF',
                '-DMI_INSTALL_TOPLEVEL=ON',
            );
        if (SPCTarget::getLibc() === 'musl') {
            $cmake->addConfigureArgs('-DMI_LIBC_MUSL=ON');
        }
        $cmake->build();
    }
}
