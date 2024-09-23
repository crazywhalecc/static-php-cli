<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

trait bzip2
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->setEnv(['CFLAGS' => $this->getLibExtraCFlags(), 'LDFLAGS' => $this->getLibExtraLdFlags(), 'LIBS' => $this->getLibExtraLibs()])
            ->execWithEnv("make PREFIX='" . BUILD_ROOT_PATH . "' clean")
            ->execWithEnv("make -j{$this->builder->concurrency} {$this->builder->getEnvString()} PREFIX='" . BUILD_ROOT_PATH . "' libbz2.a")
            ->exec('cp libbz2.a ' . BUILD_LIB_PATH)
            ->exec('cp bzlib.h ' . BUILD_INCLUDE_PATH);
    }
}
