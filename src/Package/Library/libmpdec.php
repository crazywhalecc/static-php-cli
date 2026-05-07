<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libmpdec')]
class libmpdec
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure('--disable-cxx --disable-shared --enable-static')
            ->make();
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $makefileDir = $lib->getSourceDir() . DIRECTORY_SEPARATOR . 'libmpdec';

        cmd()->cd($makefileDir)
            ->exec('copy /y Makefile.vc Makefile')
            ->exec('nmake /nologo clean')
            ->exec('nmake /nologo MACHINE=x64');

        // Copy static lib (rename from versioned name to libmpdec_a.lib)
        $libs = glob($makefileDir . DIRECTORY_SEPARATOR . 'libmpdec-*.lib');
        foreach ($libs as $libFile) {
            if (!str_contains($libFile, '.dll.')) {
                FileSystem::copy($libFile, $lib->getLibDir() . DIRECTORY_SEPARATOR . 'libmpdec_a.lib');
                break;
            }
        }

        FileSystem::copy($makefileDir . DIRECTORY_SEPARATOR . 'mpdecimal.h', $lib->getIncludeDir() . DIRECTORY_SEPARATOR . 'mpdecimal.h');
    }
}
