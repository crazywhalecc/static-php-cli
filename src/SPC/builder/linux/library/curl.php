<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

class curl extends LinuxLibraryBase
{
    public const NAME = 'curl';

    protected array $static_libs = ['libcurl.a'];

    protected array $headers = ['curl'];

    protected array $pkgconfs = [
        'libcurl.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include
supported_protocols="DICT FILE FTP FTPS GOPHER GOPHERS HTTP HTTPS IMAP IMAPS MQTT POP3 POP3S RTSP SCP SFTP SMB SMBS SMTP SMTPS TELNET TFTP"
supported_features="AsynchDNS GSS-API HSTS HTTP2 HTTPS-proxy IDN IPv6 Kerberos Largefile NTLM NTLM_WB PSL SPNEGO SSL TLS-SRP UnixSockets alt-svc brotli libz zstd"

Name: libcurl
URL: https://curl.se/
Description: Library to transfer files with ftp, http, etc.
Version: 7.83.0
Libs: -L${libdir} -lcurl
Libs.private: -lnghttp2 -lidn2 -lssh2 -lssh2 -lpsl -lssl -lcrypto -lssl -lcrypto -lgssapi_krb5 -lzstd -lbrotlidec -lz
Cflags: -I${includedir}
EOF
    ];

    protected array $dep_names = [
        'zlib' => false,
        'libssh2' => true,
        'brotli' => true,
        'nghttp2' => true,
        'zstd' => true,
        'openssl' => true,
        'idn2' => true,
        'psl' => true,
    ];

    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true): string
    {
        $libs = parent::getStaticLibFiles($style, $recursive);
        if ($this->builder->getLib('openssl')) {
            $libs .= ' -ldl -lpthread';
        }
        return $libs;
    }

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        $extra = '';
        // lib:openssl
        $openssl = $this->builder->getLib('openssl');
        $use_openssl = $openssl instanceof LinuxLibraryBase ? 'ON' : 'OFF';
        $extra .= "-DCURL_USE_OPENSSL={$use_openssl} -DCURL_ENABLE_SSL={$use_openssl} ";
        // lib:zlib
        $zlib = $this->builder->getLib('zlib');
        if ($zlib instanceof LinuxLibraryBase) {
            $extra .= '-DZLIB_LIBRARY="' . $zlib->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZLIB_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        }
        // lib:libssh2
        $libssh2 = $this->builder->getLib('libssh2');
        if ($libssh2 instanceof LinuxLibraryBase) {
            $extra .= '-DLIBSSH2_LIBRARY="' . $libssh2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DLIBSSH2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_USE_LIBSSH2=OFF ';
        }
        // lib:brotli
        $brotli = $this->builder->getLib('brotli');
        if ($brotli) {
            $extra .= '-DCURL_BROTLI=ON ' .
                '-DBROTLIDEC_LIBRARY="' . realpath(BUILD_LIB_PATH . '/libbrotlidec-static.a') . ';' . realpath(BUILD_LIB_PATH . '/libbrotlicommon-static.a') . '" ' .
                '-DBROTLICOMMON_LIBRARY="' . realpath(BUILD_LIB_PATH . '/libbrotlicommon-static.a') . '" ' .
                '-DBROTLI_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_BROTLI=OFF ';
        }
        // lib:nghttp2
        $nghttp2 = $this->builder->getLib('nghttp2');
        if ($nghttp2 instanceof LinuxLibraryBase) {
            $extra .= '-DUSE_NGHTTP2=ON ' .
                '-DNGHTTP2_LIBRARY="' . $nghttp2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DNGHTTP2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DUSE_NGHTTP2=OFF ';
        }
        // lib:ldap
        $ldap = $this->builder->getLib('ldap');
        if ($ldap instanceof LinuxLibraryBase) {
            // $extra .= '-DCURL_DISABLE_LDAP=OFF ';
            // TODO: LDAP support
            throw new RuntimeException('LDAP support is not implemented yet');
        }
        $extra .= '-DCURL_DISABLE_LDAP=ON ';
        // lib:zstd
        $zstd = $this->builder->getLib('zstd');
        if ($zstd instanceof LinuxLibraryBase) {
            $extra .= '-DCURL_ZSTD=ON ' .
                '-DZstd_LIBRARY="' . $zstd->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZstd_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_ZSTD=OFF ';
        }
        // lib:idn2
        $idn2 = $this->builder->getLib('idn2');
        $extra .= $idn2 instanceof LinuxLibraryBase ? '-DUSE_LIBIDN2=ON ' : '-DUSE_LIBIDN2=OFF ';
        // lib:psl
        $libpsl = $this->builder->getLib('psl');
        $extra .= $libpsl instanceof LinuxLibraryBase ? '-DCURL_USE_LIBPSL=ON ' : '-DCURL_USE_LIBPSL=OFF ';

        [$lib, $include, $destdir] = SEPARATED_PATH;
        // compile！
        shell()
            ->cd($this->source_dir)
            ->exec('rm -rf build')
            ->exec('mkdir -p build')
            ->cd($this->source_dir . '/build')
            ->exec("{$this->builder->configure_env} cmake " .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_CURL_EXE=OFF ' .
                $extra .
                "-DCMAKE_INSTALL_PREFIX={$destdir} " .
                "-DCMAKE_INSTALL_LIBDIR={$lib} " .
                "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '..')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR='{$destdir}'");
        shell()->cd(BUILD_LIB_PATH . '/cmake/CURL/')
            ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"" . BUILD_LIB_PATH . "/libcurl.a\"|g' CURLTargets-release.cmake");
    }
}
