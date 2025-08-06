<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait libtiff
{
    protected function build(): void
    {
        $libcpp = SPCTarget::getTargetOS() === 'Linux' ? '-lstdc++' : '-lc++';
        FileSystem::replaceFileStr($this->source_dir . '/configure', '-lwebp', '-lwebp -lsharpyuv');
        FileSystem::replaceFileStr($this->source_dir . '/configure', '-l"$lerc_lib_name"', '-l"$lerc_lib_name" ' . $libcpp);
        UnixAutoconfExecutor::create($this)
            ->optionalLib('lerc', '--enable-lerc', '--disable-lerc')
            ->optionalLib('zstd', '--enable-zstd', '--disable-zstd')
            ->optionalLib('libwebp', '--enable-webp', '--disable-webp')
            ->optionalLib('xz', '--enable-lzma', '--disable-lzma')
            ->optionalLib('jbig', '--enable-jbig', '--disable-jbig')
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
                '--disable-tools',
                '--disable-contrib',
                '--disable-cxx',
                '--without-x',
            )
            ->make();
        $this->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
