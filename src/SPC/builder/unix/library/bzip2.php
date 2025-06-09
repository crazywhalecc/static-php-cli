<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait bzip2
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'CFLAGS=-Wall', 'CFLAGS=-fPIC -Wall');
        return true;
    }

    protected function build(): void
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec("make PREFIX='" . BUILD_ROOT_PATH . "' clean")
            ->exec("make -j{$this->builder->concurrency} {$this->builder->getEnvString()} PREFIX='" . BUILD_ROOT_PATH . "' libbz2.a")
            ->exec('cp libbz2.a ' . BUILD_LIB_PATH)
            ->exec('cp bzlib.h ' . BUILD_INCLUDE_PATH);
    }
}
