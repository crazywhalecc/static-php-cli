<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait zstd
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        FileSystem::resetDir($this->source_dir . '/build/cmake/build');
        shell()->cd($this->source_dir . '/build/cmake/build')
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags(),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv(
                "cmake {$this->builder->makeCmakeArgs()} " .
                '-DZSTD_BUILD_STATIC=ON ' .
                '-DZSTD_BUILD_SHARED=OFF ' .
                '..'
            )
            ->execWithEnv("cmake --build . -j {$this->builder->concurrency}")
            ->execWithEnv('make install');
        $this->patchPkgconfPrefix(['libzstd.pc']);
    }
}
