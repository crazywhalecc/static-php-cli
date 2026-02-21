<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCConfigUtil;

trait curl
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)->exec('sed -i.save s@\${CMAKE_C_IMPLICIT_LINK_LIBRARIES}@@ ./CMakeLists.txt');

        $linkerFlags = null;
        if ($this->builder->getLib('krb5') && !getenv('SPC_LINK_STATIC')) {
            $util = new SPCConfigUtil($this->builder, ['libs_only_deps' => true]);
            $config = $util->config(libraries: ['krb5']);
            $linkerFlags = $config['LDFLAGS'] . ' -Wl,--whole-archive -lgssapi_krb5 -lkrb5 -lk5crypto -lcom_err -lkrb5support -Wl,--no-whole-archive -Wl,--no-undefined';
        }

        UnixCMakeExecutor::create($this)
            ->optionalLib('openssl', '-DCURL_USE_OPENSSL=ON -DCURL_CA_BUNDLE=OFF -DCURL_CA_PATH=OFF -DCURL_CA_FALLBACK=ON', '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF')
            ->optionalLib('brotli', ...cmake_boolean_args('CURL_BROTLI'))
            ->optionalLib('libssh2', ...cmake_boolean_args('CURL_USE_LIBSSH2'))
            ->optionalLib('nghttp2', ...cmake_boolean_args('USE_NGHTTP2'))
            ->optionalLib('nghttp3', ...cmake_boolean_args('USE_NGHTTP3'))
            ->optionalLib('ngtcp2', ...cmake_boolean_args('USE_NGTCP2'))
            ->optionalLib('ldap', ...cmake_boolean_args('CURL_DISABLE_LDAP', true))
            ->optionalLib('zstd', ...cmake_boolean_args('CURL_ZSTD'))
            ->optionalLib('idn2', ...cmake_boolean_args('USE_LIBIDN2'))
            ->optionalLib('psl', ...cmake_boolean_args('CURL_USE_LIBPSL'))
            ->optionalLib('krb5', ...cmake_boolean_args('CURL_USE_GSSAPI'))
            ->optionalLib('idn2', ...cmake_boolean_args('CURL_USE_IDN2'))
            ->optionalLib('libcares', '-DENABLE_ARES=ON')
            ->addConfigureArgs(
                '-DBUILD_CURL_EXE=OFF',
                '-DBUILD_LIBCURL_DOCS=OFF',
                $linkerFlags ? "-DCMAKE_SHARED_LINKER_FLAGS='{$linkerFlags}'" : null
            )
            ->build();

        // patch pkgconf
        $this->patchPkgconfPrefix(['libcurl.pc']);
        shell()->cd(BUILD_LIB_PATH . '/cmake/CURL/')
            ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"" . BUILD_LIB_PATH . "/libcurl.a\"|g' CURLTargets-release.cmake");
    }
}
