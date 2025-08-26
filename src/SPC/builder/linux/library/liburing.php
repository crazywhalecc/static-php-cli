<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\WrongUsageException;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

class liburing extends LinuxLibraryBase
{
    public const NAME = 'liburing';

    protected function build(): void
    {
        if (SPCTarget::getLibc() === 'glibc' && SPCTarget::getLibcVersion() < 2.30) {
            throw new WrongUsageException('liburing requires glibc >= 2.30');
        }

        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => '-D_GNU_SOURCE',
            ])
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
