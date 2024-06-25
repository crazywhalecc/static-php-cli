<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait libtiff
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        $shell = shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static --disable-shared ' .
                '--disable-cxx ' .
                '--prefix='
            );

        // TODO: Remove this check when https://gitlab.com/libtiff/libtiff/-/merge_requests/635 will be merged and released
        if (file_exists($this->source_dir . '/html')) {
            $shell->exec('make clean');
        }

        $shell
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
