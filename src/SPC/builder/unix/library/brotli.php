<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait brotli
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        FileSystem::resetDir($this->source_dir . '/build-dir');
        shell()->cd($this->source_dir . '/build-dir')
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                'cmake ' .
                "{$this->builder->makeCmakeArgs()} " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '..'
            )
            ->execWithEnv("cmake --build . -j {$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libbrotlicommon.pc', 'libbrotlidec.pc', 'libbrotlienc.pc']);
        shell()->cd(BUILD_ROOT_PATH . '/lib')->exec('ln -sf libbrotlicommon.a libbrotli.a');
        foreach (FileSystem::scanDirFiles(BUILD_ROOT_PATH . '/lib/', false, true) as $filename) {
            if (str_starts_with($filename, 'libbrotli') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink(BUILD_ROOT_PATH . '/lib/' . $filename);
            }
        }

        if (file_exists(BUILD_BIN_PATH . '/brotli')) {
            unlink(BUILD_BIN_PATH . '/brotli');
        }
    }
}
