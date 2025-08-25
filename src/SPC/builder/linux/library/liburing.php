<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\util\executor\UnixAutoconfExecutor;

class liburing extends LinuxLibraryBase
{
    public const NAME = 'liburing';

    protected function build(): void
    {
        // Build liburing with static linking via autoconf
        UnixAutoconfExecutor::create($this)
            ->removeConfigureArgs(
                '--disable-shared',
                '--enable-static',
                '--with-pic',
                '--enable-pic',
            )
            ->addConfigureArgs(
                '--use-libc'
            )
            ->configure()
            ->make(with_clean: false)
            ->exec("rm -rf {$this->getLibDir()}/*.so*");

        $this->patchPkgconfPrefix(['liburing.pc', 'liburing-ffi.pc']);
    }
}
