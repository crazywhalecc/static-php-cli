<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Package\LibraryPackage;

#[Library('fastlz')]
class fastlz
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        $cc = getenv('CC') ?: 'cc';
        $ar = getenv('AR') ?: 'ar';

        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec("{$cc} -c -O3 -fPIC fastlz.c -o fastlz.o")
            ->exec("{$ar} rcs libfastlz.a fastlz.o");

        // Copy header file
        if (!copy($lib->getSourceDir() . '/fastlz.h', $lib->getIncludeDir() . '/fastlz.h')) {
            throw new BuildFailureException('Failed to copy fastlz.h');
        }

        // Copy static library
        if (!copy($lib->getSourceDir() . '/libfastlz.a', $lib->getLibDir() . '/libfastlz.a')) {
            throw new BuildFailureException('Failed to copy libfastlz.a');
        }
    }
}
