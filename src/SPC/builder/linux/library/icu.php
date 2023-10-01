<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class icu extends LinuxLibraryBase
{
    public const NAME = 'icu';

    protected function build(): void
    {
        $root = BUILD_ROOT_PATH;
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $cppflag = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1  -DU_STATIC_IMPLEMENTATION=1"';
        shell()->cd($this->source_dir . '/source')
            ->exec(
                "{$this->builder->configure_env} {$cppflag} ./runConfigureICU Linux " .
                '--enable-static ' .
                '--disable-shared ' .
                '--with-data-packaging=static ' .
                '--enable-release=yes ' .
                '--enable-extras=yes ' .
                '--enable-icuio=yes ' .
                '--enable-dyload=no ' .
                '--enable-tools=yes ' .
                '--enable-tests=no ' .
                '--enable-samples=no ' .
                "--prefix={$root}"
            )
            ->exec('make clean')
            ->exec("LD_LIBRARY_PATH=/usr/local/musl/{$arch}/lib make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
