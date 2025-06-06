<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait ngtcp2
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $args = $this->builder->makeAutoconfArgs(static::NAME, [
            'zlib' => null,
            'openssl' => null,
            'libxml2' => null,
            'libev' => null,
            'jemalloc' => null,
        ]);

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
                $args . ' ' .
                '--prefix='
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libngtcp2.pc']);
        $this->patchPkgconfPrefix(['libngtcp2_crypto_ossl.pc']);
        $this->patchLaDependencyPrefix(['libngtcp2.la', 'libngtcp2_crypto_ossl.la']);
    }
}
