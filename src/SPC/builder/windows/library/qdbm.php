<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\store\FileSystem;

class qdbm extends WindowsLibraryBase
{
    public const NAME = 'qdbm';

    protected function build(): void
    {
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper('nmake'),
                '/f VCMakefile'
            );
        copy($this->source_dir . '\qdbm_a.lib', BUILD_LIB_PATH . '\qdbm_a.lib');
        copy($this->source_dir . '\depot.h', BUILD_INCLUDE_PATH . '\depot.h');
        // FileSystem::copyDir($this->source_dir . '\include\curl', BUILD_INCLUDE_PATH . '\curl');
    }
}
