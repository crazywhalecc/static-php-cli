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
        $extra_libs = $this->builder->getLib('libpng') ? '--with-png' : '--without-png';
        $extra_libs .= ' ';
        $extra_libs .= $this->builder->getLib('bzip2') ? ('--with-bzip2=' . BUILD_ROOT_PATH) : '--without-bzip2';
        $extra_libs .= ' ';
        $extra_libs .= $this->builder->getLib('brotli') ? ('--with-brotli=' . BUILD_ROOT_PATH) : '--without-brotli';
        $extra_libs .= ' ';

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --without-harfbuzz --prefix= ' . $extra_libs)
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['freetype2.pc']);
        FileSystem::replaceFileStr(
            BUILD_ROOT_PATH . '/lib/pkgconfig/freetype2.pc',
            ' -L/lib ',
            ' -L' . BUILD_ROOT_PATH . '/lib '
        );

        $this->cleanLaFiles();
    }
}
