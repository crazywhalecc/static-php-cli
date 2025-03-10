<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait gmssl
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        // CMake needs a clean build directory
        FileSystem::resetDir($this->source_dir . '/build');
        // Start build
        shell()->cd($this->source_dir . '/build')
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv("cmake {$this->builder->makeCmakeArgs()} -DBUILD_SHARED_LIBS=OFF ..")
            ->execWithEnv("cmake --build . -j {$this->builder->concurrency}")
            ->execWithEnv('make install');
    }
}
