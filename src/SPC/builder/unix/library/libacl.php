<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libacl
{
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/sapi/fpm/config.m4', '[AS_VAR_APPEND([FPM_EXTRA_LIBS],', ',');
        return true;
    }

    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        $cflags = PHP_OS_FAMILY !== 'Linux' ? '-Wimplicit-function-declaration -Wno-int-conversion' : '';
        $ldflags = !($this instanceof LinuxLibraryBase) ? '' : '--static';
        shell()->cd($this->source_dir)
            ->setEnv([
                'CFLAGS' => trim('-I' . BUILD_INCLUDE_PATH . ' ' . $this->getLibExtraCFlags() . ' ' . $cflags),
                'LDFLAGS' => trim('-L' . BUILD_LIB_PATH . ' ' . $this->getLibExtraLdFlags() . ' ' . $ldflags),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv('./autogen.sh')
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --disable-tests --disable-nls')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libacl.pc'], PKGCONF_PATCH_PREFIX);
    }
}
