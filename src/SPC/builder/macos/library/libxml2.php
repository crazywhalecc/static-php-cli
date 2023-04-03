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

class libxml2 extends MacOSLibraryBase
{
    public const NAME = 'libxml2';

    /**
     * @throws RuntimeException
     */
    protected function build()
    {
        $enable_zlib = $this->builder->getLib('zlib') ? 'ON' : 'OFF';
        $enable_icu = $this->builder->getLib('icu') ? 'ON' : 'OFF';
        $enable_xz = $this->builder->getLib('xz') ? 'ON' : 'OFF';

        [$lib, $include, $destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec('rm -rf build')
            ->exec('mkdir -p build')
            ->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} " . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DLIBXML2_WITH_ICONV=ON ' .
                "-DLIBXML2_WITH_ZLIB={$enable_zlib} " .
                "-DLIBXML2_WITH_ICU={$enable_icu} " .
                "-DLIBXML2_WITH_LZMA={$enable_xz} " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
                '-DLIBXML2_WITH_TESTS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                "-DCMAKE_INSTALL_LIBDIR={$lib} " .
                "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '..'
            )
            ->exec("cmake --build . -j {$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
    }
}
