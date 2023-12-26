<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;

trait libcares
{
    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec('./configure --prefix=' . BUILD_ROOT_PATH . ' --enable-static --disable-shared --disable-tests')
            ->exec("make -j {$this->builder->concurrency}")
            ->exec('make install');
    }
}
