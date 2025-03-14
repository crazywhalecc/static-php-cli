<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class libffi extends MacOSLibraryBase
{
    public const NAME = 'libffi';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        [, , $destdir] = SEPARATED_PATH;
        $arch = getenv('SPC_ARCH');
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$arch}-apple-darwin " .
                "--target={$arch}-apple-darwin " .
                '--prefix= ' // use prefix=/
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
