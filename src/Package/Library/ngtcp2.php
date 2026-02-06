<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('ngtcp2')]
class ngtcp2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->optionalPackage(
                'openssl',
                fn (LibraryPackage $openssl) => implode(' ', [
                    '--with-openssl=yes',
                    "OPENSSL_LIBS=\"{$openssl->getStaticLibFiles()}\"",
                    "OPENSSL_CFLAGS=\"-I{$openssl->getIncludeDir()}\"",
                ]),
                '--with-openssl=no'
            )
            ->optionalPackage('nghttp3', ...ac_with_args('libnghttp3', true))
            ->optionalPackage(
                'brotli',
                fn (LibraryPackage $brotli) => implode(' ', [
                    '--with-brotlidec=yes',
                    "LIBBROTLIDEC_CFLAGS=\"-I{$brotli->getIncludeDir()}\"",
                    "LIBBROTLIDEC_LIBS=\"{$brotli->getStaticLibFiles()}\"",
                    '--with-libbrotlienc=yes',
                    "LIBBROTLIENC_CFLAGS=\"-I{$brotli->getIncludeDir()}\"",
                    "LIBBROTLIENC_LIBS=\"{$brotli->getStaticLibFiles()}\"",
                ])
            )
            ->appendEnv(['PKG_CONFIG' => '$PKG_CONFIG --static'])
            ->configure('--enable-lib-only')
            ->make();

        $lib->patchPkgconfPrefix(['libngtcp2.pc', 'libngtcp2_crypto_ossl.pc'], PKGCONF_PATCH_PREFIX);

        // On macOS, the static library may contain other static libraries
        // ld: archive member 'libssl.a' not a mach-o file in libngtcp2_crypto_ossl.a
        $AR = getenv('AR') ?: 'ar';
        shell()->cd($lib->getLibDir())->exec("{$AR} -t libngtcp2_crypto_ossl.a | grep '\\.a\$' | xargs -n1 {$AR} d libngtcp2_crypto_ossl.a");
    }
}
