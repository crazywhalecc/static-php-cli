<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class icu extends MacOSLibraryBase
{
    public const NAME = 'icu';

    protected function build(): void
    {
        $root = BUILD_ROOT_PATH;

        $cxxflags = 'CXXFLAGS="-std=c++17"';
        shell()->cd($this->source_dir . '/source')
            ->exec("{$cxxflags} ./runConfigureICU MacOSX --enable-static --disable-shared --prefix={$root}")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
