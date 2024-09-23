<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libcares
{
    public function patchBeforeBuild(): bool
    {
        if (!file_exists($this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h')) {
            FileSystem::createDir($this->source_dir . '/src/lib/thirdparty/apple');
            copy(ROOT_DIR . '/src/globals/extra/libcares_dnsinfo.h', $this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h');
            return true;
        }
        return false;
    }

    /**
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv('./configure --prefix= --enable-static --disable-shared --disable-tests')
            ->execWithEnv("make -j {$this->builder->concurrency}")
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libcares.pc'], PKGCONF_PATCH_PREFIX);
    }
}
