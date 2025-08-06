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

use SPC\util\executor\UnixAutoconfExecutor;

class libpng extends MacOSLibraryBase
{
    public const NAME = 'libpng';

    protected function build(): void
    {
        $arch = arch2gnu(php_uname('m'));
        UnixAutoconfExecutor::create($this)
            ->exec('chmod +x ./configure')
            ->exec('chmod +x ./install-sh')
            ->appendEnv(['LDFLAGS' => "-L{$this->getLibDir()}"])
            ->configure(
                "--host={$arch}-apple-darwin",
                '--enable-hardware-optimizations',
                "--with-zlib-prefix={$this->getBuildRootPath()}",
                match (getenv('SPC_ARCH')) {
                    'x86_64' => '--enable-intel-sse',
                    'aarch64' => '--enable-arm-neon',
                    default => '',
                }
            )
            ->make('libpng16.la', 'install-libLTLIBRARIES install-data-am', after_env_vars: ['DEFAULT_INCLUDES' => "-I{$this->source_dir} -I{$this->getIncludeDir()}"]);

        shell()->cd(BUILD_LIB_PATH)->exec('ln -sf libpng16.a libpng.a');
        $this->patchPkgconfPrefix(['libpng16.pc'], PKGCONF_PATCH_PREFIX);
        $this->patchLaDependencyPrefix();
    }
}
