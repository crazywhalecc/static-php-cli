<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait qdbm
{
    protected function build(): void
    {
        $ac = UnixAutoconfExecutor::create($this)->configure();
        FileSystem::replaceFileRegex($this->source_dir . '/Makefile', '/MYLIBS = libqdbm.a.*/m', 'MYLIBS = libqdbm.a');
        $extra = trim((string) getenv('SPC_DEFAULT_C_FLAGS'));
        FileSystem::replaceFileRegex($this->source_dir . '/Makefile', '/^CFLAGS = .*$/m', "CFLAGS = -Wall {$extra}");
        $ac->make($this instanceof MacOSLibraryBase ? 'mac' : '');
        $this->patchPkgconfPrefix(['qdbm.pc']);
    }
}
