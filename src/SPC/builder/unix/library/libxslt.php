<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait libxslt
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static --disable-shared ' .
                '--without-python ' .
                '--without-mem-debug ' .
                '--without-crypto ' .
                '--without-debug ' .
                '--without-debugger ' .
                '--with-libxml-prefix=' . escapeshellarg(BUILD_ROOT_PATH) . ' ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . escapeshellarg(BUILD_ROOT_PATH));
        $this->patchPkgconfPrefix(['libexslt.pc']);
    }
}
