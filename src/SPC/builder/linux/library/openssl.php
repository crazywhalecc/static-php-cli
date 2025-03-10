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

use SPC\builder\linux\SystemUtil;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

class openssl extends LinuxLibraryBase
{
    public const NAME = 'openssl';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function build(): void
    {
        [,,$destdir] = SEPARATED_PATH;

        $extra = '';
        $ex_lib = '-ldl -pthread';

        $env = "CC='" . getenv('CC') . ' -idirafter ' . BUILD_INCLUDE_PATH .
            ' -idirafter /usr/include/ ' .
            ' -idirafter /usr/include/' . $this->builder->getOption('arch') . '-linux-gnu/ ' .
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

        $clang_postfix = SystemUtil::getCCType(getenv('CC')) === 'clang' ? '-clang' : '';

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags() ?: $this->builder->arch_c_flags, 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                "{$env} ./Configure no-shared {$extra} " .
                '--prefix=/ ' .
                '--libdir=lib ' .
                '--openssldir=/etc/ssl ' .
                "{$zlib_extra}" .
                'no-legacy ' .
                "linux-{$this->builder->getOption('arch')}{$clang_postfix}"
            )
            ->exec('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency} CNF_EX_LIBS=\"{$ex_lib}\"")
            ->exec("make install_sw DESTDIR={$destdir}");
        $this->patchPkgconfPrefix(['libssl.pc', 'openssl.pc', 'libcrypto.pc']);
        // patch for openssl 3.3.0+
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/libssl.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/libssl.pc', 'prefix=${pcfiledir}/../..' . "\n" . $file);
        }
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/openssl.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/openssl.pc', 'prefix=${pcfiledir}/../..' . "\n" . $file);
        }
        if (!str_contains($file = FileSystem::readFile(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc'), 'prefix=')) {
            FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc', 'prefix=${pcfiledir}/../..' . "\n" . $file);
        }
        FileSystem::replaceFileRegex(BUILD_LIB_PATH . '/pkgconfig/libcrypto.pc', '/Libs.private:.*/m', 'Libs.private: ${libdir}/libz.a');
        FileSystem::replaceFileRegex(BUILD_LIB_PATH . '/cmake/OpenSSL/OpenSSLConfig.cmake', '/set\(OPENSSL_LIBCRYPTO_DEPENDENCIES .*\)/m', 'set(OPENSSL_LIBCRYPTO_DEPENDENCIES "${OPENSSL_LIBRARY_DIR}/libz.a")');
    }
}
