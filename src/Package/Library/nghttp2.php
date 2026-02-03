<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('nghttp2')]
class nghttp2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->optionalPackage('zlib', ...ac_with_args('zlib', true))
            ->optionalPackage('openssl', ...ac_with_args('openssl', true))
            ->optionalPackage('libxml2', ...ac_with_args('libxml2', true))
            ->optionalPackage('ngtcp2', ...ac_with_args('libngtcp2', true))
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
            ->configure('--enable-lib-only')
            ->make();

        $lib->patchPkgconfPrefix(['libnghttp2.pc'], PKGCONF_PATCH_PREFIX);
    }
}
