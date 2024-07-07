<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class icu extends MacOSLibraryBase
{
    public const NAME = 'icu';

    protected function build(): void
    {
        $root = BUILD_ROOT_PATH;
        shell()->cd($this->source_dir . '/source')
            ->exec("./runConfigureICU MacOSX --enable-static --disable-shared --disable-extras --disable-samples --disable-tests --disable-tools --prefix={$root}")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
