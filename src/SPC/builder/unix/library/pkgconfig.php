<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait pkgconfig
{
    protected function build(): void
    {
        $macos_env = 'PKG_CONFIG_PATH="' . BUILD_LIB_PATH . '/pkgconfig/" ' .
            "CC='{$this->builder->getOption('cc')}' " .
            "CXX='{$this->builder->getOption('cxx')}' " .
            "CFLAGS='{$this->builder->arch_c_flags} -Wimplicit-function-declaration' ";
        $linux_env = 'PKG_CONFIG_PATH="' . BUILD_LIB_PATH . '/pkgconfig" ' .
            "CC='{$this->builder->getOption('cc')}' " .
            "CXX='{$this->builder->getOption('cxx')}' ";

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
                '--prefix=' . BUILD_ROOT_PATH . ' ' .
                '--without-sysroot ' .
                '--without-system-include-path ' .
                '--without-system-library-path ' .
                '--without-pc-path'
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
