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

class brotli extends LinuxLibraryBase
{
    public const NAME = 'brotli';

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;
        f_passthru(
            "{$this->builder->set_x} && " .
            "cd {$this->source_dir} && " .
            'rm -rf build && ' .
            'mkdir -p build && ' .
            'cd build && ' .
            "{$this->builder->configure_env} " . ' cmake ' .
            // '--debug-find ' .
            '-DCMAKE_BUILD_TYPE=Release ' .
            '-DBUILD_SHARED_LIBS=OFF ' .
            '-DCMAKE_INSTALL_PREFIX=/ ' .
            "-DCMAKE_INSTALL_LIBDIR={$lib} " .
            "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
            "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
            '.. && ' .
            "cmake --build . -j {$this->builder->concurrency} --target brotlicommon-static && " .
            "cmake --build . -j {$this->builder->concurrency} --target brotlidec-static && " .
            "cmake --build . -j {$this->builder->concurrency} --target brotlienc-static && " .
            'cp libbrotlidec-static.a ' . BUILD_LIB_PATH . ' && ' .
            'cp libbrotlienc-static.a ' . BUILD_LIB_PATH . ' && ' .
            'cp libbrotlicommon-static.a ' . BUILD_LIB_PATH . ' && ' .
            'cp -r ../c/include/brotli ' . BUILD_INCLUDE_PATH
        );
    }
}
