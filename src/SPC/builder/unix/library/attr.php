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
        $cflags = PHP_OS_FAMILY !== 'Linux' ? "{$this->builder->arch_c_flags} -Wimplicit-function-declaration -Wno-int-conversion" : '';
        $ldflags = PHP_OS_FAMILY !== 'Linux' ? '' : '--static';
        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags() . ' ' . $cflags),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags() . ' ' . $ldflags),
                'LIBS' => $this->getLibExtraLibs(),
            ])->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libattr.pc'], PKGCONF_PATCH_PREFIX);
    }
}
