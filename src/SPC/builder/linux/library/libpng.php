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

use SPC\builder\linux\SystemUtil;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class libpng extends LinuxLibraryBase
{
    public const NAME = 'libpng';

    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFile(
            SOURCE_PATH . '/libpng/configure',
            REPLACE_FILE_STR,
            '-lz',
            BUILD_LIB_PATH . '/libz.a'
        );
        if (SystemUtil::getOSRelease()['dist'] === 'alpine') {
            FileSystem::replaceFile(
                SOURCE_PATH . '/libpng/configure',
                REPLACE_FILE_STR,
                '-lm',
                '/usr/lib/libm.a'
            );
        }
        return true;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        $optimizations = match ($this->builder->arch) {
            'x86_64' => '--enable-intel-sse ',
            'arm64' => '--enable-arm-neon ',
            default => '',
        };
        shell()->cd($this->source_dir)
            ->exec('chmod +x ./configure')
            ->exec('chmod +x ./install-sh')
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                "--host={$this->builder->gnu_arch}-unknown-linux " .
                '--disable-shared ' .
                '--enable-static ' .
                '--enable-hardware-optimizations ' .
                '--with-zlib-prefix="' . BUILD_ROOT_PATH . '" ' .
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
