<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait nghttp2
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('zlib', ...ac_with_args('zlib', true))
            ->optionalLib('openssl', ...ac_with_args('openssl', true))
            ->optionalLib('libxml2', ...ac_with_args('libxml2', true))
            ->optionalLib('libev', ...ac_with_args('libev', true))
            ->optionalLib('libcares', ...ac_with_args('libcares', true))
            ->optionalLib('ngtcp2', ...ac_with_args('libngtcp2', true))
            ->optionalLib('nghttp3', ...ac_with_args('libnghttp3', true))
            // ->optionalLib('libbpf', ...ac_with_args('libbpf', true))
            // ->optionalLib('libevent-openssl', ...ac_with_args('libevent-openssl', true))
            // ->optionalLib('jansson', ...ac_with_args('jansson', true))
            // ->optionalLib('jemalloc', ...ac_with_args('jemalloc', true))
            // ->optionalLib('systemd', ...ac_with_args('systemd', true))
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
        $this->patchPkgconfPrefix(['libnghttp2.pc']);
        $this->patchLaDependencyPrefix();
    }
}
