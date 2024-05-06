<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait xz
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-scripts ' .
                '--disable-doc ' .
                '--with-libiconv ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['liblzma.pc']);
    }
}
