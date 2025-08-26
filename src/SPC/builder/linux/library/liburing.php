<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

class liburing extends LinuxLibraryBase
{
    public const NAME = 'liburing';

    protected function build(): void
    {
        $use_libc = SPCTarget::getLibc() !== 'glibc' || version_compare(SPCTarget::getLibcVersion(), '2.30', '>=');
        $make = UnixAutoconfExecutor::create($this);

        if (!$use_libc) {
            $make->appendEnv([
                'CC' => 'gcc', // libc-less version fails to compile with clang or zig
                'CXX' => 'g++',
                'AR' => 'ar',
                'LD' => 'ld',
            ]);
        } else {
            $make->appendEnv([
                'CFLAGS' => '-D_GNU_SOURCE',
            ]);
        }

        $make
            ->removeConfigureArgs(
                '--disable-shared',
                '--enable-static',
                '--with-pic',
                '--enable-pic',
            )
            ->addConfigureArgs(
                $use_libc ? '--use-libc' : '',
            )
            ->configure()
            ->make(with_clean: false)
            ->exec("rm -rf {$this->getLibDir()}/liburing*.so*");

        $this->patchPkgconfPrefix(['liburing.pc', 'liburing-ffi.pc']);
    }
}
