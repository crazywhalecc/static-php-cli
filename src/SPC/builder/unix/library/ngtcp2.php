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
        if ($brotli = $this->builder->getLib('brotli')) {
            /* @phpstan-ignore-next-line */
            $args .= ' --with-libbrotlidec=yes LIBBROTLIDEC_CFLAGS="-I' . BUILD_ROOT_PATH . '/include" LIBBROTLIDEC_LIBS="' . $brotli->getStaticLibFiles() . '"';
            /* @phpstan-ignore-next-line */
            $args .= ' --with-libbrotlienc=yes LIBBROTLIENC_CFLAGS="-I' . BUILD_ROOT_PATH . '/include" LIBBROTLIENC_LIBS="' . $brotli->getStaticLibFiles() . '"';
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

        // on macOS, the static library may contain other static libraries?
        // ld: archive member 'libssl.a' not a mach-o file in libngtcp2_crypto_ossl.a
        shell()->cd(BUILD_LIB_PATH)
            ->exec("ar -t libngtcp2_crypto_ossl.a | grep '\.a$' | xargs -n1 ar d libngtcp2_crypto_ossl.a");
    }
}
