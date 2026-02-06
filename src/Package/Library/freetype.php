<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('freetype')]
class freetype
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $cmake = UnixCMakeExecutor::create($lib)
            ->optionalPackage('libpng', ...cmake_boolean_args('FT_DISABLE_PNG', true))
            ->optionalPackage('bzip2', ...cmake_boolean_args('FT_DISABLE_BZIP2', true))
            ->optionalPackage('brotli', ...cmake_boolean_args('FT_DISABLE_BROTLI', true))
            ->addConfigureArgs('-DFT_DISABLE_HARFBUZZ=ON');

        // fix cmake 4.0 compatibility
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $cmake->addConfigureArgs('-DCMAKE_POLICY_VERSION_MINIMUM=3.12');
        }

        $cmake->build();

        $lib->patchPkgconfPrefix(['freetype2.pc']);
        FileSystem::replaceFileStr("{$lib->getBuildRootPath()}/lib/pkgconfig/freetype2.pc", ' -L/lib ', " -L{$lib->getBuildRootPath()}/lib ");
    }
}
