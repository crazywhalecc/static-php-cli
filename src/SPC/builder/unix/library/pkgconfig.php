<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait pkgconfig
{
    protected function build(): void
    {
        $macos_env = "CFLAGS='{$this->builder->arch_c_flags} -Wimplicit-function-declaration' ";
        $linux_env = 'LDFLAGS=--static ';

        shell()->cd($this->source_dir)
            ->exec(
                match (PHP_OS_FAMILY) {
                    'Darwin' => $macos_env,
                    default => $linux_env,
                } .
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
