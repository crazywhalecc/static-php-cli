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

class libpng extends MacOSLibraryBase
{
    public const NAME = 'libpng';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $optimizations = match (php_uname('m')) {
            'x86_64' => '--enable-intel-sse ',
            'arm64' => '--enable-arm-neon ',
            default => '',
        };
        shell()->cd($this->source_dir)
            ->exec('chmod +x ./configure')
            ->exec('chmod +x ./install-sh')
            ->exec(
                './configure ' .
                '--host=' . arch2gnu(php_uname('m')) . '-apple-darwin ' .
                '--disable-shared ' .
                '--enable-static ' .
                '--enable-hardware-optimizations ' .
                $optimizations .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency} DEFAULT_INCLUDES='-I. -I" . BUILD_INCLUDE_PATH . "' LIBS= libpng16.la")
            ->exec('make install-libLTLIBRARIES install-data-am DESTDIR=' . BUILD_ROOT_PATH)
            ->cd(BUILD_LIB_PATH)
            ->exec('ln -sf libpng16.a libpng.a');
        $this->patchPkgconfPrefix(['libpng16.pc'], PKGCONF_PATCH_PREFIX);
        $this->cleanLaFiles();
    }
}
