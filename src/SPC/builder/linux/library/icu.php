<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\store\FileSystem;

class icu extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\icu;

    public const NAME = 'icu';

    protected function build(): void
    {
        $cppflags = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1 -DU_STATIC_IMPLEMENTATION=1 -fPIC -fPIE -fno-ident"';
        $cxxflags = 'CXXFLAGS="-std=c++17"';
        $ldflags = getenv('SPC_LIBC') !== 'glibc' ? 'LDFLAGS="-static"' : '';
        shell()->cd($this->source_dir . '/source')
            ->exec(
                "{$cppflags} {$cxxflags} {$ldflags} " .
                './runConfigureICU Linux ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--with-data-packaging=static ' .
                '--enable-release=yes ' .
                '--enable-extras=no ' .
                '--enable-icuio=yes ' .
                '--enable-dyload=no ' .
                '--enable-tools=yes ' .
                '--enable-tests=no ' .
                '--enable-samples=no ' .
                '--prefix=' . BUILD_ROOT_PATH
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');

        $this->patchPkgconfPrefix(['icu-i18n.pc', 'icu-io.pc', 'icu-uc.pc'], PKGCONF_PATCH_PREFIX);
        FileSystem::removeDir(BUILD_LIB_PATH . '/icu');
    }
}
