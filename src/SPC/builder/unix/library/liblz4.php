<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait liblz4
{
    public function patchBeforeBuild(): bool
    {
        // disable executables
        FileSystem::replaceFileStr($this->source_dir . '/programs/Makefile', 'install: lz4', "install: lz4\n\ninstallewfwef: lz4");
        return true;
    }

    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec("make PREFIX='' clean")
            ->exec("make -j{$this->builder->concurrency} PREFIX=''")
            ->exec("make install PREFIX='' DESTDIR=" . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['liblz4.pc']);

        foreach (FileSystem::scanDirFiles(BUILD_ROOT_PATH . '/lib/', false, true) as $filename) {
            if (str_starts_with($filename, 'liblz4') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink(BUILD_ROOT_PATH . '/lib/' . $filename);
            }
        }
    }
}
