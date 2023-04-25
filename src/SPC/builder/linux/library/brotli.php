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
        // 使用 cmake 编译
        shell()->cd($this->source_dir)
            ->exec(
                <<<EOF
                {$this->builder->configure_env}
                test -d build && rm -rf build 
                mkdir -p build 
                cd build 
                cmake .. \\
                -DCMAKE_BUILD_TYPE=Release \\
                -DCMAKE_INSTALL_PREFIX={$destdir} \\
                -DBUILD_SHARED_LIBS=OFF  \\
                -DBUILD_STATIC_LIBS=ON \\
                -DBROTLI_SHARED_LIBS=OFF \\
                -DBROTLI_STATIC_LIBS=ON \\
                -DBROTLI_DISABLE_TESTS=OFF \\
                -DBROTLI_BUNDLED_MODE=OFF \\
                -DBROTLI_EMSCRIPTEN=OFF
                
                cmake --build . --config Release --target install 
                
               # -DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file}
               # -DCMAKE_INSTALL_LIBDIR={$destdir}/lib \\
               # -DCMAKE_INSTALL_INCLUDEDIR={$destdir}/include \\
EOF
            )
            ->exec(
                <<<EOF
            cp  -f {$destdir}/lib/libbrotlicommon-static.a {$destdir}/lib/libbrotli.a
            mv     {$destdir}/lib/libbrotlicommon-static.a {$destdir}/lib/libbrotlicommon.a
            mv     {$destdir}/lib/libbrotlienc-static.a    {$destdir}/lib/libbrotlienc.a
            mv     {$destdir}/lib/libbrotlidec-static.a    {$destdir}/lib/libbrotlidec.a
            rm -rf {$destdir}/lib/*.so.*
            rm -rf {$destdir}/lib/*.so
            rm -rf {$destdir}/lib/*.dylib
EOF
            );
    }
}
