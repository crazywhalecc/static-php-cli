<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
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
        $available = [
            'openssl' => null,
            'libev' => null,
            'jemalloc' => null,
        ];
        if (PHP_OS_FAMILY === 'Linux') {
            $available = [...$available, ...[
                'zlib' => null,
                'libxml2' => null,
            ]];
        }
        $args = $this->builder->makeAutoconfArgs(static::NAME, $available);
        if (PHP_OS_FAMILY === 'Darwin') {
            $args = str_replace('=yes', '=' . BUILD_ROOT_PATH, $args);
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
