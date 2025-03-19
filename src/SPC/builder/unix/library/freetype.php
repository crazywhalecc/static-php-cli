<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

trait freetype
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $extra_libs = $this->builder->getLib('libpng') ? '-DFT_DISABLE_PNG=OFF ' : '-DFT_DISABLE_PNG=ON ';
        $extra_libs .= $this->builder->getLib('bzip2') ? '-DFT_DISABLE_BZIP2=OFF ' : '-DFT_DISABLE_BZIP2=ON ';
        $extra_libs .= $this->builder->getLib('brotli') ? '-DFT_DISABLE_BROTLI=OFF ' : '-DFT_DISABLE_BROTLI=ON ';
        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                "cmake {$this->builder->makeCmakeArgs()} -DFT_DISABLE_HARFBUZZ=ON " .
                '-DBUILD_SHARED_LIBS=OFF ' .
                "{$extra_libs}.."
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install');

        $this->patchPkgconfPrefix(['freetype2.pc']);
        FileSystem::replaceFileStr(
            BUILD_ROOT_PATH . '/lib/pkgconfig/freetype2.pc',
            ' -L/lib ',
            ' -L' . BUILD_ROOT_PATH . '/lib '
        );

        $this->cleanLaFiles();
    }
}
