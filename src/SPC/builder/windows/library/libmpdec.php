<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

class libmpdec extends WindowsLibraryBase
{
    public const NAME = 'libmpdec';

    protected function build(): void
    {
        $makefile_dir = $this->source_dir . '\libmpdec';
        $nmake = $this->builder->makeSimpleWrapper('nmake /nologo');

        cmd()->cd($makefile_dir)
            ->exec('copy /y Makefile.vc Makefile')
            ->execWithWrapper($nmake, 'clean')
            ->execWithWrapper($nmake, 'MACHINE=x64');

        // Copy static lib (rename from versioned name to libmpdec_a.lib)
        $libs = glob($makefile_dir . '\libmpdec-*.lib');
        foreach ($libs as $lib) {
            if (!str_contains($lib, '.dll.')) {
                copy($lib, BUILD_LIB_PATH . '\libmpdec_a.lib');
                break;
            }
        }
        copy($makefile_dir . '\mpdecimal.h', BUILD_INCLUDE_PATH . '\mpdecimal.h');
    }
}
