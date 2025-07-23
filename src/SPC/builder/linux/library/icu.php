<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\store\FileSystem;
use SPC\util\SPCTarget;

class icu extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\icu;

    public const NAME = 'icu';

    protected function build(): void
    {
        $cppflags = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1 -DU_STATIC_IMPLEMENTATION=1 -DPIC -fPIC"';
        $cxxflags = 'CXXFLAGS="-std=c++17 -DPIC -fPIC -fno-ident"';
        $ldflags = SPCTarget::isStatic() ? 'LDFLAGS="-static"' : '';
        shell()->cd($this->source_dir . '/source')->initializeEnv($this)
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

        $this->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX);
        FileSystem::removeDir(BUILD_LIB_PATH . '/icu');
    }
}
