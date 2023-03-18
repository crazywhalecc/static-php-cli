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

class bzip2 extends MacOSLibraryBase
{
    public const NAME = 'bzip2';

    protected function build()
    {
        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            "make {$this->builder->configure_env} PREFIX='" . BUILD_ROOT_PATH . "' clean" . ' && ' .
            "make -j{$this->builder->concurrency} {$this->builder->configure_env} PREFIX='" . BUILD_ROOT_PATH . "' libbz2.a" . ' && ' .
            // make install may fail when cross-compiling, so we copy files.
            'cp libbz2.a ' . BUILD_LIB_PATH . '  && ' .
            'cp bzlib.h ' . BUILD_INCLUDE_PATH
        );
    }
}
