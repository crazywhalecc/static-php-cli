<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait qdbm
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static --disable-shared ' .
                '--prefix='
            )
            ->exec('make clean');
        FileSystem::replaceFileRegex($this->source_dir . '/Makefile', '/MYLIBS = libqdbm.a.*/m', 'MYLIBS = libqdbm.a');
        shell()->cd($this->source_dir)
            ->exec("make -j{$this->builder->concurrency}" . ($this instanceof MacOSLibraryBase ? ' mac' : ''))
            ->exec('make install DESTDIR=' . BUILD_ROOT_PATH);
        $this->patchPkgconfPrefix(['qdbm.pc']);
    }
}
