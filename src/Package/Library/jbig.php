<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Util\FileSystem;

#[Library('jbig')]
class jbig
{
    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        FileSystem::replaceFileStr($lib->getSourceDir() . '/Makefile', 'CFLAGS = -O2 -W -Wno-unused-result', 'CFLAGS = -O2 -W -Wno-unused-result -fPIC');
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, PackageBuilder $builder): void
    {
        $ccenv = [
            'CC' => getenv('CC'),
            'CXX' => getenv('CXX'),
            'AR' => getenv('AR'),
            'LD' => getenv('LD'),
        ];
        $env = [];
        foreach ($ccenv as $k => $v) {
            $env[] = "{$k}={$v}";
        }
        $env_str = implode(' ', $env);
        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec("make -j{$builder->concurrency} {$env_str} lib")
            ->exec("cp libjbig/libjbig.a {$lib->getLibDir()}")
            ->exec("cp libjbig/libjbig85.a {$lib->getLibDir()}")
            ->exec("cp libjbig/jbig.h {$lib->getIncludeDir()}")
            ->exec("cp libjbig/jbig85.h {$lib->getIncludeDir()}")
            ->exec("cp libjbig/jbig_ar.h {$lib->getIncludeDir()}");
    }
}
