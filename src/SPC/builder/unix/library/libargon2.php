<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait libargon2
{
    protected function build()
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec("make PREFIX='' clean")
            ->exec("make -j{$this->builder->concurrency} PREFIX=''")
            ->exec("make install PREFIX='' DESTDIR=" . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libargon2.pc']);

        foreach (FileSystem::scanDirFiles(BUILD_ROOT_PATH . '/lib/', false, true) as $filename) {
            if (str_starts_with($filename, 'libargon2') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink(BUILD_ROOT_PATH . '/lib/' . $filename);
            }
        }

        if (file_exists(BUILD_BIN_PATH . '/argon2')) {
            unlink(BUILD_BIN_PATH . '/argon2');
        }
    }
}
