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

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\Patcher;

class libpng extends LinuxLibraryBase
{
    public const NAME = 'libpng';

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        [$lib, $include, $destdir] = SEPARATED_PATH;
        // 不同架构的专属优化
        $optimizations = match ($this->builder->arch) {
            'x86_64' => '--enable-intel-sse ',
            'arm64' => '--enable-arm-neon ',
            default => '',
        };

        // patch configure
        // Patcher::patchUnixLibpng();

        shell()->cd($this->source_dir)
            ->exec('make clean')
            ->exec('chmod +x ./configure')
            ->exec(
                <<<EOF
                {$this->builder->configure_env} 
                CPPFLAGS="$(pkg-config  --cflags-only-I  --static zlib )" \\
                LDFLAGS="$(pkg-config   --libs-only-L    --static zlib )" \\
                LIBS="$(pkg-config      --libs-only-l    --static zlib )" \\
                ./configure  \\
                --prefix={$destdir} \\
                --host={$this->builder->gnu_arch}-unknown-linux  \\
                --disable-shared  \\
                --enable-static  \\
                --enable-hardware-optimizations  \\
                --with-zlib-prefix={$destdir}  \\
                {$optimizations} 
EOF
            )
            ->exec('make -j ' . $this->builder->concurrency)
            ->exec('make install ');
    }
}
