<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\linux\SystemUtil;

class icu extends LinuxLibraryBase
{
    public const NAME = 'icu';

    protected function build(): void
    {
        $cppflags = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1  -DU_STATIC_IMPLEMENTATION=1"';
        $cxxflags = 'CXXFLAGS="-std=c++11"';
        $ldflags = 'LDFLAGS="-static"';
        $libs = SystemUtil::getOSRelease()['dist'] === 'alpine' ? '' : 'LIBS="/usr/local/musl/lib/libstdc++.a"';
        shell()->cd($this->source_dir . '/source')
            ->exec(
                "{$this->builder->configure_env} {$cppflags} {$cxxflags} {$ldflags} {$libs} " .
                './runConfigureICU Linux ' .
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
                '--prefix=' . BUILD_ROOT_PATH
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
