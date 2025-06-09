<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;

trait pkgconfig
{
    protected function build(): void
    {
        $cflags = PHP_OS_FAMILY !== 'Linux' ? "{$this->builder->arch_c_flags} -Wimplicit-function-declaration -Wno-int-conversion" : '';
        $ldflags = !($this instanceof LinuxLibraryBase) || getenv('SPC_LIBC') === 'glibc' ? '' : '--static';

        shell()->cd($this->source_dir)->initLibBuildEnv($this)
            ->appendEnv(['CFLAGS' => $cflags, 'LDFLAGS' => $ldflags])
            ->exec(
                './configure ' .
                '--disable-shared ' .
                '--enable-static ' .
                '--with-internal-glib ' .
                '--disable-host-tool ' .
                '--with-pic ' .
                '--prefix=' . BUILD_ROOT_PATH . ' ' .
                '--without-sysroot ' .
                '--without-system-include-path ' .
                '--without-system-library-path ' .
                '--without-pc-path'
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install-exec');
        shell()->exec('strip ' . BUILD_ROOT_PATH . '/bin/pkg-config');
    }
}
