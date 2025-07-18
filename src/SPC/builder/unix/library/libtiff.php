<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait libtiff
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        FileSystem::replaceFileStr($this->source_dir . '/configure', '-lwebp', '-lwebp -lsharpyuv');
        UnixAutoconfExecutor::create($this)
            ->configure(
                // zlib deps
                '--enable-zlib',
                "--with-zlib-include-dir={$this->getIncludeDir()}",
                "--with-zlib-lib-dir={$this->getLibDir()}",
                // libjpeg deps
                '--enable-jpeg',
                "--with-jpeg-include-dir={$this->getIncludeDir()}",
                "--with-jpeg-lib-dir={$this->getLibDir()}",
                '--disable-old-jpeg',
                '--disable-jpeg12',
                '--disable-libdeflate',
                '--disable-cxx',
                '--without-x',
            )
            ->optionalLib('lerc', '--enable-lerc', '--disable-lerc')
            ->optionalLib('zstd', '--enable-zstd', '--disable-zstd')
            ->optionalLib('webp', '--enable-webp', '--disable-webp')
            ->optionalLib('xz', '--enable-lzma', '--disable-lzma')
            ->make();
        $this->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
