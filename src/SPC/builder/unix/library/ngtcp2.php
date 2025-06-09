<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\executor\UnixAutoconfExecutor;

trait ngtcp2
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('openssl', ...ac_with_args('openssl', true))
            ->optionalLib('libev', ...ac_with_args('libev', true))
            ->optionalLib('nghttp3', ...ac_with_args('libnghttp3', true))
            ->optionalLib('jemalloc', ...ac_with_args('jemalloc', true))
            ->optionalLib(
                'brotli',
                fn ($lib) => implode(' ', [
                    '--with-brotlidec=yes',
                    "LIBBROTLIDEC_CFLAGS=\"-I{$lib->getIncludeDir()}\"",
                    "LIBBROTLIDEC_LIBS=\"{$lib->getStaticLibFiles()}\"",
                    '--with-libbrotlienc=yes',
                    "LIBBROTLIENC_CFLAGS=\"-I{$lib->getIncludeDir()}\"",
                    "LIBBROTLIENC_LIBS=\"{$lib->getStaticLibFiles()}\"",
                ])
            )
            ->configure('--enable-lib-only')
            ->make();
        $this->patchPkgconfPrefix(['libngtcp2.pc', 'libngtcp2_crypto_ossl.pc']);
        $this->patchLaDependencyPrefix(['libngtcp2.la', 'libngtcp2_crypto_ossl.la']);

        // on macOS, the static library may contain other static libraries?
        // ld: archive member 'libssl.a' not a mach-o file in libngtcp2_crypto_ossl.a
        shell()->cd(BUILD_LIB_PATH)
            ->exec("ar -t libngtcp2_crypto_ossl.a | grep '\\.a$' | xargs -n1 ar d libngtcp2_crypto_ossl.a");
    }
}
