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

namespace SPC\builder\macos\library;

use SPC\exception\RuntimeException;

class curl extends MacOSLibraryBase
{
    public const NAME = 'curl';

    protected function build()
    {
        $extra = '';
        // lib:openssl
        $openssl = $this->getBuilder()->getLib('openssl');
        if ($openssl instanceof MacOSLibraryBase) {
            $extra .= '-DCURL_USE_OPENSSL=ON ';
        } else {
            $extra .= '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF ';
        }
        // lib:zlib
        $zlib = $this->getBuilder()->getLib('zlib');
        if ($zlib instanceof MacOSLibraryBase) {
            $extra .= '-DZLIB_LIBRARY="' . $zlib->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZLIB_INCLUDE_DIR=' . BUILD_INCLUDE_PATH . ' ';
        }
        // lib:libssh2
        $libssh2 = $this->builder->getLib('libssh2');
        if ($libssh2 instanceof MacOSLibraryBase) {
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
        if ($nghttp2 instanceof MacOSLibraryBase) {
            $extra .= '-DUSE_NGHTTP2=ON ' .
                '-DNGHTTP2_LIBRARY="' . $nghttp2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DNGHTTP2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DUSE_NGHTTP2=OFF ';
        }
        // lib:ldap
        $ldap = $this->builder->getLib('ldap');
        if ($ldap instanceof MacOSLibraryBase) {
            // $extra .= '-DCURL_DISABLE_LDAP=OFF ';
            // TODO: LDAP support
            throw new RuntimeException('LDAP support is not implemented yet');
        }
        $extra .= '-DCURL_DISABLE_LDAP=ON ';
        // lib:zstd
        $zstd = $this->builder->getLib('zstd');
        if ($zstd instanceof MacOSLibraryBase) {
            $extra .= '-DCURL_ZSTD=ON ' .
                '-DZstd_LIBRARY="' . $zstd->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZstd_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_ZSTD=OFF ';
        }
        // lib:idn2
        $idn2 = $this->builder->getLib('idn2');
        $extra .= $idn2 instanceof MacOSLibraryBase ? '-DUSE_LIBIDN2=ON ' : '-DUSE_LIBIDN2=OFF ';
        // lib:psl
        $libpsl = $this->builder->getLib('psl');
        $extra .= $libpsl instanceof MacOSLibraryBase ? '-DCURL_USE_LIBPSL=ON ' : '-DCURL_USE_LIBPSL=OFF ';

        [$lib, $include, $destdir] = SEPARATED_PATH;
        // compile！
        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            'rm -rf build && ' .
            'mkdir -p build && ' .
            'cd build && ' .
            "{$this->builder->configure_env} " . ' cmake ' .
            // '--debug-find ' .
            '-DCMAKE_BUILD_TYPE=Release ' .
            '-DBUILD_SHARED_LIBS=OFF ' .
            $extra .
            '-DCMAKE_INSTALL_PREFIX= ' .
            "-DCMAKE_INSTALL_LIBDIR={$lib} " .
            "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
            "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
            '.. && ' .
            "make -j{$this->builder->concurrency} && " .
            'make install DESTDIR="' . $destdir . '"'
        );
    }
}
