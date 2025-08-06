<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\util\executor\UnixAutoconfExecutor;

trait ngtcp2
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('openssl', fn (LinuxLibraryBase|MacOSLibraryBase $lib) => implode(' ', [
                '--with-openssl=yes',
                "OPENSSL_LIBS=\"{$lib->getStaticLibFiles()}\"",
                "OPENSSL_CFLAGS=\"-I{$lib->getIncludeDir()}\"",
            ]), '--with-openssl=no')
            ->optionalLib('libev', ...ac_with_args('libev', true))
            ->optionalLib('nghttp3', ...ac_with_args('libnghttp3', true))
            ->optionalLib('jemalloc', ...ac_with_args('jemalloc', true))
            ->optionalLib(
                'brotli',
                fn (LinuxLibraryBase|MacOSLibraryBase $lib) => implode(' ', [
                    '--with-brotlidec=yes',
                    "LIBBROTLIDEC_CFLAGS=\"-I{$lib->getIncludeDir()}\"",
                    "LIBBROTLIDEC_LIBS=\"{$lib->getStaticLibFiles()}\"",
                    '--with-libbrotlienc=yes',
                    "LIBBROTLIENC_CFLAGS=\"-I{$lib->getIncludeDir()}\"",
                    "LIBBROTLIENC_LIBS=\"{$lib->getStaticLibFiles()}\"",
                ])
            )
            ->appendEnv(['PKG_CONFIG' => '$PKG_CONFIG --static'])
            ->configure('--enable-lib-only')
            ->make();
        $this->patchPkgconfPrefix(['libngtcp2.pc', 'libngtcp2_crypto_ossl.pc']);
        $this->patchLaDependencyPrefix();

        // on macOS, the static library may contain other static libraries?
        // ld: archive member 'libssl.a' not a mach-o file in libngtcp2_crypto_ossl.a
        $AR = getenv('AR') ?: 'ar';
        shell()->cd(BUILD_LIB_PATH)->exec("{$AR} -t libngtcp2_crypto_ossl.a | grep '\\.a$' | xargs -n1 {$AR} d libngtcp2_crypto_ossl.a");
    }
}
