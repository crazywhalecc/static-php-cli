<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait readline
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static=yes ' .
                '--enable-shared=no ' .
                '--prefix= ' .
                '--with-curses ' .
                '--enable-multibyte=yes'
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['readline.pc']);
    }
}
