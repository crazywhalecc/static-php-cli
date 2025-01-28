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
use SPC\exception\WrongUsageException;

class libpng extends LinuxLibraryBase
{
    public const NAME = 'libpng';

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function build(): void
    {
        $optimizations = match ($this->builder->getOption('arch')) {
            'x86_64' => '--enable-intel-sse ',
            'arm64' => '--enable-arm-neon ',
            default => '',
        };
        shell()->cd($this->source_dir)
            ->exec('chmod +x ./configure')
            ->exec('chmod +x ./install-sh')
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags() ?: $this->builder->arch_c_flags, 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv(
                'LDFLAGS="-L' . BUILD_LIB_PATH . '" ' .
                './configure ' .
                '--disable-shared ' .
                '--enable-static ' .
                '--enable-hardware-optimizations ' .
                '--with-zlib-prefix="' . BUILD_ROOT_PATH . '" ' .
                $optimizations .
                '--prefix='
            )
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency} DEFAULT_INCLUDES='-I{$this->source_dir} -I" . BUILD_INCLUDE_PATH . "' LIBS= libpng16.la")
            ->execWithEnv('make install-libLTLIBRARIES install-data-am DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['libpng16.pc'], PKGCONF_PATCH_PREFIX);
        $this->cleanLaFiles();
    }
}
