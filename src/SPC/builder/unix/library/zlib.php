<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait zlib
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv('./configure --static --prefix=')
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv("make install DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['zlib.pc']);
    }
}
