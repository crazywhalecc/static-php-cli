<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Util\FileSystem;

#[Library('liblz4')]
class liblz4
{
    #[PatchBeforeBuild]
    #[PatchDescription('Fix Makefile install target for static liblz4')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        FileSystem::replaceFileStr($lib->getSourceDir() . '/programs/Makefile', 'install: lz4', "install: lz4\n\ninstallewfwef: lz4");
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, PackageBuilder $builder): void
    {
        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec("make PREFIX='' clean")
            ->exec("make lib -j{$builder->concurrency} PREFIX=''");

        FileSystem::replaceFileStr("{$lib->getSourceDir()}/Makefile", '$(MAKE) -C $(PRGDIR) $@', '');

        shell()->cd($lib->getSourceDir())
            ->exec("make install PREFIX='' DESTDIR={$lib->getBuildRootPath()}");

        $lib->patchPkgconfPrefix(['liblz4.pc']);

        foreach (FileSystem::scanDirFiles($lib->getLibDir(), false, true) as $filename) {
            if (str_starts_with($filename, 'liblz4') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink("{$lib->getLibDir()}/{$filename}");
            }
        }
    }
}
