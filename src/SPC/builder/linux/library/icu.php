<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class icu extends LinuxLibraryBase
{
    public const NAME = 'icu';

    protected function build()
    {
        $root = BUILD_ROOT_PATH;
        shell()->cd($this->source_dir . '/source')
            ->exec("{$this->builder->configure_env} ./runConfigureICU Linux --enable-static --disable-shared --prefix={$root}")
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
