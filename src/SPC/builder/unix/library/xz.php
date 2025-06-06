<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait xz
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
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
                '--enable-pic ' .
                '--disable-scripts ' .
                '--disable-doc ' .
                '--with-libiconv ' .
                '--prefix='
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['liblzma.pc']);
    }
}
