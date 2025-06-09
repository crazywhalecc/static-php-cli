<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\util\executor\UnixAutoconfExecutor;

trait pkgconfig
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => PHP_OS_FAMILY !== 'Linux' ? '-Wimplicit-function-declaration -Wno-int-conversion' : '',
                'LDFLAGS' => !($this instanceof LinuxLibraryBase) || getenv('SPC_LIBC') === 'glibc' ? '' : '--static',
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
