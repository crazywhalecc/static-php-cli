<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait icu
{
    protected function build()
    {
        $os = $this->os;
        $root = BUILD_ROOT_PATH;
        $cppflag = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1  -DU_STATIC_IMPLEMENTATION=1"';
        shell()->cd($this->source_dir . '/source')
            ->exec(
                "{$this->builder->configure_env} {$cppflag} ./runConfigureICU " . $os . ' ' .
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
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
