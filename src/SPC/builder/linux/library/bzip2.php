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

class bzip2 extends LinuxLibraryBase
{
    public const NAME = 'bzip2';

    protected array $dep_names = [];

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        shell()
            ->cd($this->source_dir)
            ->exec(
                <<<'EOF'
        if [[ -f blocksort.o ]]
        then
            make clean
        fi
EOF
            );

        shell()
            ->cd($this->source_dir)
            ->exec(
                $this->builder->configure_env .
                "make -j{$this->builder->concurrency}  PREFIX='" . BUILD_ROOT_PATH . "' libbz2.a"
            )
            ->exec('cp libbz2.a ' . BUILD_LIB_PATH)
            ->exec('cp bzlib.h ' . BUILD_INCLUDE_PATH);
    }
}
