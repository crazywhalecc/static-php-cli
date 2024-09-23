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
        $suggested = $this->builder->getLib('libpng') ? '--with-png' : '--without-png';
        $suggested .= ' ';
        $suggested .= $this->builder->getLib('bzip2') ? ('--with-bzip2=' . BUILD_ROOT_PATH) : '--without-bzip2';
        $suggested .= ' ';
        $suggested .= $this->builder->getLib('brotli') ? ('--with-brotli=' . BUILD_ROOT_PATH) : '--without-brotli';
        $suggested .= ' ';

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->exec('sh autogen.sh')
            ->execWithEnv(
                './configure ' .
                '--enable-static --disable-shared --without-harfbuzz --prefix= ' .
                $suggested
            )
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
