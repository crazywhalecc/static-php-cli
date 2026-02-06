<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;

#[Library('bzip2')]
class bzip2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib, PackageBuilder $builder): void
    {
        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec("make PREFIX='{$lib->getBuildRootPath()}' clean")
            ->exec("make -j{$builder->concurrency} PREFIX='{$lib->getBuildRootPath()}' libbz2.a")
            ->exec('cp libbz2.a ' . $lib->getLibDir())
            ->exec('cp bzlib.h ' . $lib->getIncludeDir());
    }
}
