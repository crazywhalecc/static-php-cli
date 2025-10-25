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

use SPC\store\FileSystem;

class openssl extends LinuxLibraryBase
{
    use \SPC\builder\traits\openssl;

    public const NAME = 'openssl';

    public function build(): void
    {
        $extra = '';
        $ex_lib = '-ldl -pthread';
        $arch = getenv('SPC_ARCH');

        $env = "CC='" . getenv('CC') . ' -idirafter ' . BUILD_INCLUDE_PATH .
            ' -idirafter /usr/include/ ' .
            ' -idirafter /usr/include/' . getenv('SPC_ARCH') . '-linux-gnu/ ' .
            "' ";
        // lib:zlib
        $zlib = $this->builder->getLib('zlib');
        if ($zlib instanceof LinuxLibraryBase) {
            $extra = 'zlib';
            $ex_lib = trim($zlib->getStaticLibFiles() . ' ' . $ex_lib);
            $zlib_extra =
                '--with-zlib-include=' . BUILD_INCLUDE_PATH . ' ' .
                '--with-zlib-lib=' . BUILD_LIB_PATH . ' ';
        } else {
            $zlib_extra = '';
        }

        $ex_lib = trim($ex_lib);

        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec(
                "{$env} ./Configure no-shared {$extra} " .
                '--prefix=' . BUILD_ROOT_PATH . ' ' .
                '--libdir=' . BUILD_LIB_PATH . ' ' .
                '--openssldir=/etc/ssl ' .
                "{$zlib_extra}" .
                'enable-pie ' .
                'no-legacy ' .
                "linux-{$arch}"
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency} CNF_EX_LIBS=\"{$ex_lib}\"")
            ->exec('make install_sw');
        $this->patchPkgconfPrefix(['libssl.pc', 'openssl.pc', 'libcrypto.pc']);
        // patch for openssl 3.3.0+
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/libssl.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/libssl.pc', 'prefix=' . BUILD_ROOT_PATH . "\n" . $file);
        }
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/openssl.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/openssl.pc', 'prefix=' . BUILD_ROOT_PATH . "\n" . $file);
        }
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc', 'prefix=' . BUILD_ROOT_PATH . "\n" . $file);
        }
        FileSystem::replaceFileRegex(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc', '/Libs.private:.*/m', 'Requires.private: zlib');
        FileSystem::replaceFileRegex(BUILD_LIB_PATH . '/cmake/OpenSSL/OpenSSLConfig.cmake', '/set\(OPENSSL_LIBCRYPTO_DEPENDENCIES .*\)/m', 'set(OPENSSL_LIBCRYPTO_DEPENDENCIES "${OPENSSL_LIBRARY_DIR}/libz.a")');
    }
}
