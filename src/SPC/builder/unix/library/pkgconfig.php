<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait pkgconfig
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => '-Wimplicit-function-declaration -Wno-int-conversion',
                'LDFLAGS' => SPCTarget::isStatic() ? '--static' : '',
            ])
            ->configure(
                '--with-internal-glib',
                '--disable-host-tool',
                '--without-sysroot',
                '--without-system-include-path',
                '--without-system-library-path',
                '--without-pc-path',
            )
            ->make(with_install: 'install-exec');

        shell()->exec('strip ' . BUILD_ROOT_PATH . '/bin/pkg-config');
    }
}
