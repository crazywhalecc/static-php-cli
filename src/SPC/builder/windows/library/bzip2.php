<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

class bzip2 extends WindowsLibraryBase
{
    public const NAME = 'bzip2';

    protected function build(): void
    {
        $nmake = $this->builder->makeSimpleWrapper('nmake /nologo /f Makefile.msc CFLAGS="-DWIN32 -MT -Ox -D_FILE_OFFSET_BITS=64 -nologo"');
        cmd()->cd($this->source_dir)
            ->execWithWrapper($nmake, 'clean')
            ->execWithWrapper($nmake, 'lib');
        copy($this->source_dir . '\libbz2.lib', BUILD_LIB_PATH . '\libbz2.lib');
        copy($this->source_dir . '\libbz2.lib', BUILD_LIB_PATH . '\libbz2_a.lib');
        copy($this->source_dir . '\bzlib.h', BUILD_INCLUDE_PATH . '\bzlib.h');
    }
}
