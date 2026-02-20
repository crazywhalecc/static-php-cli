<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait libargon2
{
    protected function build()
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec("make PREFIX='' clean")
            ->exec("make -j{$this->builder->concurrency} PREFIX=''")
            ->exec("make install PREFIX='' DESTDIR=" . BUILD_ROOT_PATH);

        $this->patchPkgconfPrefix(['libargon2.pc']);

        if (file_exists(BUILD_BIN_PATH . '/argon2')) {
            unlink(BUILD_BIN_PATH . '/argon2');
        }
    }
}
