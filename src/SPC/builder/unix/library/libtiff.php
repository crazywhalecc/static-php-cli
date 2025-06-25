<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait libtiff
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                // zlib deps
                '--enable-zlib',
                "--with-zlib-include-dir={$this->getIncludeDir()}",
                "--with-zlib-lib-dir={$this->getLibDir()}",
                // libjpeg deps
                '--enable-jpeg',
                '--disable-old-jpeg',
                '--disable-jpeg12',
                "--with-jpeg-include-dir={$this->getIncludeDir()}",
                "--with-jpeg-lib-dir={$this->getLibDir()}",
                // We disabled lzma, zstd, webp, libdeflate by default to reduce the size of the binary
                '--disable-lzma',
                '--disable-zstd',
                '--disable-webp',
                '--disable-libdeflate',
                '--disable-cxx',
            )
            ->make();
        $this->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
