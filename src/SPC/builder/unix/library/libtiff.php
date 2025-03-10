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
        // zlib
        $extra_libs = '--enable-zlib --with-zlib-include-dir=' . BUILD_ROOT_PATH . '/include --with-zlib-lib-dir=' . BUILD_ROOT_PATH . '/lib';
        // libjpeg
        $extra_libs .= ' --enable-jpeg --disable-old-jpeg --disable-jpeg12 --with-jpeg-include-dir=' . BUILD_ROOT_PATH . '/include --with-jpeg-lib-dir=' . BUILD_ROOT_PATH . '/lib';
        // We disabled lzma, zstd, webp, libdeflate by default to reduce the size of the binary
        $extra_libs .= ' --disable-lzma --disable-zstd --disable-webp --disable-libdeflate';

        $shell = shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                './configure ' .
                '--enable-static --disable-shared ' .
                "{$extra_libs} " .
                '--disable-cxx ' .
                '--prefix='
            );

        // TODO: Remove this check when https://gitlab.com/libtiff/libtiff/-/merge_requests/635 will be merged and released
        if (file_exists($this->source_dir . '/html')) {
            $shell->execWithEnv('make clean');
        }

        $shell
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libtiff-4.pc']);
    }
}
