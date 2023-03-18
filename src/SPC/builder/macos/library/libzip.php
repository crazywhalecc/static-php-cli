<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * static-php-cli is licensed under Mulan PSL v2. You can use this
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

class libzip extends MacOSLibraryBase
{
    public const NAME = 'libzip';

    protected function build()
    {
        $extra = '';
        // lib:zlib
        $zlib = $this->builder->getLib('zlib');
        if ($zlib instanceof MacOSLibraryBase) {
            $extra .= '-DZLIB_LIBRARY="' . $zlib->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZLIB_INCLUDE_DIR=' . BUILD_INCLUDE_PATH . ' ';
        }
        // lib:bzip2
        $libbzip2 = $this->builder->getLib('bzip2');
        if ($libbzip2 instanceof MacOSLibraryBase) {
            $extra .= '-DENABLE_BZIP2=ON ' .
                '-DBZIP2_LIBRARIES="' . $libbzip2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DBZIP2_INCLUDE_DIR=' . BUILD_INCLUDE_PATH . ' ';
        } else {
            $extra .= '-DENABLE_BZIP2=OFF ';
        }
        // lib:xz
        $xz = $this->builder->getLib('xz');
        if ($xz instanceof MacOSLibraryBase) {
            $extra .= '-DENABLE_LZMA=ON ' .
                '-DLIBLZMA_LIBRARY="' . $xz->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DLIBLZMA_INCLUDE_DIR=' . BUILD_INCLUDE_PATH . ' ';
        } else {
            $extra .= '-DENABLE_LZMA=OFF ';
        }
        // lib:zstd
        $libzstd = $this->builder->getLib('zstd');
        if ($libzstd instanceof MacOSLibraryBase) {
            $extra .= '-DENABLE_ZSTD=ON ' .
                '-DZstd_LIBRARY="' . $libzstd->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZstd_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DENABLE_ZSTD=OFF ';
        }
        // lib:openssl
        $libopenssl = $this->builder->getLib('openssl');
        if ($libopenssl instanceof MacOSLibraryBase) {
            $extra .= '-DENABLE_OPENSSL=ON ' .
                '-DOpenSSL_LIBRARY="' . $libopenssl->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DOpenSSL_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DENABLE_OPENSSL=OFF ';
        }

        [$lib, $include, $destdir] = SEPARATED_PATH;

        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            'rm -rf build && ' .
            'mkdir -p build && ' .
            'cd build && ' .
            "{$this->builder->configure_env} " . ' cmake ' .
            // '--debug-find ' .
            '-DCMAKE_BUILD_TYPE=Release ' .
            '-DENABLE_GNUTLS=OFF ' .
            '-DENABLE_MBEDTLS=OFF ' .
            '-DBUILD_SHARED_LIBS=OFF ' .
            '-DBUILD_DOC=OFF ' .
            '-DBUILD_EXAMPLES=OFF ' .
            '-DBUILD_REGRESS=OFF ' .
            '-DBUILD_TOOLS=OFF ' .
            $extra .
            '-DCMAKE_INSTALL_PREFIX=/ ' .
            "-DCMAKE_INSTALL_LIBDIR={$lib} " .
            "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
            "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
            '.. && ' .
            "make -j{$this->builder->concurrency} && " .
            'make install DESTDIR=' . $destdir
        );
    }
}
