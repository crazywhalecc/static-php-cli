<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libmaxminddb
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DBUILD_TESTING=OFF',
                '-DMAXMINDDB_BUILD_BINARIES=OFF',
            )
            ->build();
    }
}
