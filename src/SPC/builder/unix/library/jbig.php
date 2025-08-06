<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait jbig
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr($this->source_dir . '/Makefile', 'CFLAGS = -O2 -W -Wno-unused-result', 'CFLAGS = -O2 -W -Wno-unused-result -fPIC');
        return true;
    }

    protected function build(): void
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec("make -j{$this->builder->concurrency} {$this->builder->getEnvString()} lib")
            ->exec('cp libjbig/libjbig.a ' . BUILD_LIB_PATH)
            ->exec('cp libjbig/libjbig85.a ' . BUILD_LIB_PATH)
            ->exec('cp libjbig/jbig.h ' . BUILD_INCLUDE_PATH)
            ->exec('cp libjbig/jbig85.h ' . BUILD_INCLUDE_PATH)
            ->exec('cp libjbig/jbig_ar.h ' . BUILD_INCLUDE_PATH);
    }
}
