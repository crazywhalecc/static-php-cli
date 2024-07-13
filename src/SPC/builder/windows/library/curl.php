<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class curl extends WindowsLibraryBase
{
    public const NAME = 'curl';

    protected function build(): void
    {
        FileSystem::createDir(BUILD_BIN_PATH);
        cmd()->cd($this->source_dir . '\winbuild')
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('nmake'),
                '/f Makefile.vc WITH_DEVEL=' . BUILD_ROOT_PATH . ' ' .
                'WITH_PREFIX=' . BUILD_ROOT_PATH . ' ' .
                'mode=static RTLIBCFG=static WITH_SSL=static WITH_NGHTTP2=static WITH_SSH2=static ENABLE_IPV6=yes WITH_ZLIB=static MACHINE=x64 DEBUG=no'
            );
        FileSystem::copyDir($this->source_dir . '\include\curl', BUILD_INCLUDE_PATH . '\curl');
    }
}
