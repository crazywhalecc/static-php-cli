<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;

trait attr
{
    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --disable-tests')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
        $this->cleanLaFiles();
    }
}
