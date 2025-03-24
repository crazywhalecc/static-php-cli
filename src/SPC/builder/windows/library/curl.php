<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class curl extends WindowsLibraryBase
{
    public const NAME = 'curl';

    protected function build(): void
    {
        // reset cmake
        FileSystem::resetDir($this->source_dir . '\cmakebuild');

        // lib:zstd
        $alt = $this->builder->getLib('zstd') ? '' : '-DCURL_ZSTD=OFF';
        // lib:brotli
        $alt .= $this->builder->getLib('brotli') ? '' : ' -DCURL_BROTLI=OFF';

        // start build
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                '-B cmakebuild ' .
                '-A x64 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_STATIC_LIBS=ON ' .
                '-DCURL_STATICLIB=ON ' .
                '-DCMAKE_INSTALL_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                '-DBUILD_CURL_EXE=OFF ' . // disable curl.exe
                '-DBUILD_TESTING=OFF ' . // disable tests
                '-DBUILD_EXAMPLES=OFF ' . // disable examples
                '-DUSE_LIBIDN2=OFF ' . // disable libidn2
                '-DCURL_USE_LIBPSL=OFF ' . // disable libpsl
                '-DCURL_USE_SCHANNEL=ON ' . // use Schannel instead of OpenSSL
                '-DCURL_USE_OPENSSL=OFF ' . // disable openssl due to certificate issue
                '-DCURL_ENABLE_SSL=ON ' .
                '-DUSE_NGHTTP2=ON ' . // enable nghttp2
                '-DCURL_USE_LIBSSH2=ON ' . // enable libssh2
                '-DENABLE_IPV6=ON ' . // enable ipv6
                '-DNGHTTP2_CFLAGS="/DNGHTTP2_STATICLIB" ' .
                $alt
            )
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('cmake'),
                "--build cmakebuild --config Release --target install -j{$this->builder->concurrency}"
            );
        // move libcurl.lib to libcurl_a.lib
        rename(BUILD_LIB_PATH . '\libcurl.lib', BUILD_LIB_PATH . '\libcurl_a.lib');
    }
}
