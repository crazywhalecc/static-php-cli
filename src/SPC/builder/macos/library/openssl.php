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

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

class openssl extends MacOSLibraryBase
{
    public const NAME = 'openssl';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        [$lib,,$destdir] = SEPARATED_PATH;

        // lib:zlib
        $extra = '';
        $ex_lib = '';
        $zlib = $this->builder->getLib('zlib');
        if ($zlib instanceof MacOSLibraryBase) {
            $extra = 'zlib';
            $ex_lib = trim($zlib->getStaticLibFiles() . ' ' . $ex_lib);
        }
        $arch = getenv('SPC_ARCH');

        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->exec(
                "./Configure no-shared {$extra} " .
                '--prefix=/ ' . // use prefix=/
                "--libdir={$lib} " .
                '--openssldir=/etc/ssl ' .
                "darwin64-{$arch}-cc"
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
