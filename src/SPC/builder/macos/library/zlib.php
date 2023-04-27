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

class zlib extends MacOSLibraryBase
{
    public const NAME = 'zlib';

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        [, , $destdir] = SEPARATED_PATH;
        shell()
            ->cd($this->source_dir)
            ->exec(
                <<<'EOF'
        if [[ -f gzlib.o ]] 
        then
            make clean
        fi
EOF
            );
        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} " . PHP_EOL .
                'CFLAGS="-fPIE -fPIC" ./configure ' .
                '--static ' .
                '--prefix=' . $destdir
            )
            ->exec("make -j{$this->builder->concurrency}")
            ->exec('make install');
    }
}
