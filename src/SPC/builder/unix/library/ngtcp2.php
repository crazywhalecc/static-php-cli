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
            'openssl' => null,
            'libev' => null,
            'jemalloc' => null,
            'libnghttp3' => null,
        ]);
        if (PHP_OS_FAMILY === 'Linux') {
            $args = preg_replace('/OPENSSL_LIBS="(.*?)"/', 'OPENSSL_LIBS="\1 -lpthread -ldl"', $args);
        }

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
        $this->patchPkgconfPrefix(['libngtcp2.pc', 'libngtcp2_crypto_ossl.pc']);
    }
}
