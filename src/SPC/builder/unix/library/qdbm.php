<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait qdbm
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        $ac = UnixAutoconfExecutor::create($this)->configure();
        FileSystem::replaceFileRegex($this->source_dir . '/Makefile', '/MYLIBS = libqdbm.a.*/m', 'MYLIBS = libqdbm.a');
        $ac->make($this instanceof MacOSLibraryBase ? 'mac' : '');
        $this->patchPkgconfPrefix(['qdbm.pc']);
    }
}
