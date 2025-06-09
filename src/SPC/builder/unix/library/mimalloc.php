<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait mimalloc
{
    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DMI_BUILD_SHARED=OFF',
                '-DMI_INSTALL_TOPLEVEL=ON'
            );
        if (getenv('SPC_LIBC') === 'musl') {
            $cmake->addConfigureArgs('-DMI_LIBC_MUSL=ON');
        }
        $cmake->build();
    }
}
