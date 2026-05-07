<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('tidy')]
class tidy
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $cmake = UnixCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/build-dir")
            ->addConfigureArgs(
                '-DSUPPORT_CONSOLE_APP=OFF',
                '-DBUILD_SHARED_LIB=OFF'
            );
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $cmake->addConfigureArgs('-DCMAKE_POLICY_VERSION_MINIMUM=3.5');
        }
        $cmake->build();
        $lib->patchPkgconfPrefix(['tidy.pc']);
    }

    #[BuildFor('Windows')]
    public function buildWindows(LibraryPackage $lib): void
    {
        $cmake = WindowsCMakeExecutor::create($lib)
            ->setBuildDir("{$lib->getSourceDir()}/build-dir")
            ->addConfigureArgs(
                '-DSUPPORT_CONSOLE_APP=OFF',
                '-DBUILD_SHARED_LIB=OFF'
            )->build();

        // rename tidy_static.lib to tidy_a.lib
        FileSystem::moveFileOrDir($lib->getLibDir() . '\tidy_static.lib', $lib->getLibDir() . '\tidy_a.lib');
    }
}
