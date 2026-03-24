<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Util\FileSystem;

#[Library('bzip2')]
class bzip2
{
    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        FileSystem::replaceFileStr($lib->getSourceDir() . '/Makefile', 'CFLAGS=-Wall', 'CFLAGS=-fPIC -Wall');
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $package): void
    {
        cmd()->cd($package->getSourceDir())
            ->exec('nmake /nologo /f Makefile.msc CFLAGS="-DWIN32 -MT -Ox -D_FILE_OFFSET_BITS=64 -nologo" clean')
            ->exec('nmake /nologo /f Makefile.msc CFLAGS="-DWIN32 -MT -Ox -D_FILE_OFFSET_BITS=64 -nologo" lib');
        FileSystem::copy("{$package->getSourceDir()}\\libbz2.lib", "{$package->getLibDir()}\\libbz2.lib");
        FileSystem::copy("{$package->getSourceDir()}\\libbz2.lib", "{$package->getLibDir()}\\libbz2_a.lib");
        FileSystem::copy("{$package->getSourceDir()}\\bzlib.h", "{$package->getIncludeDir()}\\bzlib.h");
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib, PackageBuilder $builder): void
    {
        $shell = shell()->cd($lib->getSourceDir())->initializeEnv($lib);
        $env = $shell->getEnvString();
        $cc_env = 'CC=' . escapeshellarg(getenv('CC') ?: 'cc') . ' AR=' . escapeshellarg(getenv('AR') ?: 'ar');

        $shell->exec("make PREFIX='{$lib->getBuildRootPath()}' clean")
            ->exec("make -j{$builder->concurrency} {$cc_env} {$env} PREFIX='{$lib->getBuildRootPath()}' libbz2.a")
            ->exec('cp libbz2.a ' . $lib->getLibDir())
            ->exec('cp bzlib.h ' . $lib->getIncludeDir());
    }
}
