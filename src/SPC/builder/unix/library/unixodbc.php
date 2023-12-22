<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait unixodbc
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static --disable-shared ' .
                '--disable-debug ' .
                '--disable-dependency-tracking ' .
                '--with-libiconv-prefix=' . BUILD_ROOT_PATH . ' ' .
                '--with-included-ltdl ' .
                '--enable-gui=no ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['odbc.pc', 'odbccr.pc', 'odbcinst.pc']);
        $this->cleanLaFiles();
    }
}
