<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('libargon2')]
class libargon2
{
    #[PatchBeforeBuild]
    #[PatchDescription('Fix library path for Linux builds')]
    public function patchBeforeLinuxBuild(LibraryPackage $lib): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Linux', 'Not a Linux build, skipping lib path patch.');
        FileSystem::replaceFileStr("{$lib->getSourceDir()}/Makefile", 'LIBRARY_REL ?= lib/x86_64-linux-gnu', 'LIBRARY_REL ?= lib');
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, PackageBuilder $builder): void
    {
        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec("make PREFIX='' clean")
            ->exec("make -j{$builder->concurrency} PREFIX=''")
            ->exec("make install PREFIX='' DESTDIR={$lib->getBuildRootPath()}");

        $lib->patchPkgconfPrefix(['libargon2.pc']);

        foreach (FileSystem::scanDirFiles("{$lib->getBuildRootPath()}/lib/", false, true) as $filename) {
            if (str_starts_with($filename, 'libargon2') && (str_contains($filename, '.so') || str_ends_with($filename, '.dylib'))) {
                unlink("{$lib->getBuildRootPath()}/lib/{$filename}");
            }
        }

        if (file_exists("{$lib->getBinDir()}/argon2")) {
            unlink("{$lib->getBinDir()}/argon2");
        }
    }
}
