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
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags()),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags()),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv('libtoolize --force --copy')
            ->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --with-pic --disable-nls')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
