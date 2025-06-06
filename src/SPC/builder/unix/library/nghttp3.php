<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait nghttp3
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags(),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--with-pic ' .
                '--enable-lib-only ' .
                '--prefix='
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libnghttp3.pc']);
    }
}
