<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

trait curl
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)->exec('sed -i.save s@\${CMAKE_C_IMPLICIT_LINK_LIBRARIES}@@ ./CMakeLists.txt');

        UnixCMakeExecutor::create($this)
            ->optionalLib('openssl', '-DCURL_USE_OPENSSL=ON -DCURL_CA_BUNDLE=OFF -DCURL_CA_PATH=OFF -DCURL_CA_FALLBACK=ON', '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF')
            ->optionalLib('brotli', ...cmake_boolean_args('CURL_BROTLI'))
            ->optionalLib('libssh2', fn ($lib) => "-DLIBSSH2_LIBRARY=\"{$lib->getStaticLibFiles(style: 'cmake')}\" -DLIBSSH2_INCLUDE_DIR={$lib->getIncludeDir()}", '-DCURL_USE_LIBSSH2=OFF')
            ->optionalLib('nghttp2', fn ($lib) => "-DUSE_NGHTTP2=ON -DNGHTTP2_LIBRARY=\"{$lib->getStaticLibFiles(style: 'cmake')}\" -DNGHTTP2_INCLUDE_DIR={$lib->getIncludeDir()}", '-DUSE_NGHTTP2=OFF')
            ->optionalLib('nghttp3', fn ($lib) => "-DUSE_NGHTTP3=ON -DNGHTTP3_LIBRARY=\"{$lib->getStaticLibFiles(style: 'cmake')}\" -DNGHTTP3_INCLUDE_DIR={$lib->getIncludeDir()}", '-DUSE_NGHTTP3=OFF')
            ->optionalLib('ngtcp2', fn ($lib) => "-DUSE_NGTCP2=ON -DNGNGTCP2_LIBRARY=\"{$lib->getStaticLibFiles(style: 'cmake')}\" -DNGNGTCP2_INCLUDE_DIR={$lib->getIncludeDir()}", '-DUSE_NGTCP2=OFF')
            ->optionalLib('ldap', ...cmake_boolean_args('CURL_DISABLE_LDAP', true))
            ->optionalLib('zstd', ...cmake_boolean_args('CURL_ZSTD'))
            ->optionalLib('idn2', ...cmake_boolean_args('USE_LIBIDN2'))
            ->optionalLib('psl', ...cmake_boolean_args('CURL_USE_LIBPSL'))
            ->optionalLib('libcares', '-DENABLE_ARES=ON')
            ->addConfigureArgs(
                '-DBUILD_CURL_EXE=OFF',
                '-DBUILD_LIBCURL_DOCS=OFF',
            )
            ->build();

        // patch pkgconf
        $this->patchPkgconfPrefix(['libcurl.pc']);
        shell()->cd(BUILD_LIB_PATH . '/cmake/CURL/')
            ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"" . BUILD_LIB_PATH . "/libcurl.a\"|g' CURLTargets-release.cmake");
    }
}
