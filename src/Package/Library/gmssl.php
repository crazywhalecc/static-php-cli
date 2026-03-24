<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('gmssl')]
class gmssl
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)->build();
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $buildDir = "{$lib->getSourceDir()}\\builddir";

        // GmSSL requires NMake Makefiles generator on Windows
        WindowsCMakeExecutor::create($lib)
            ->setBuildDir($buildDir)
            ->setCustomDefaultArgs(
                '-G "NMake Makefiles"',
                '-DWIN32=ON',
                '-DBUILD_SHARED_LIBS=OFF',
                '-DCMAKE_BUILD_TYPE=Release',
                '-DCMAKE_C_FLAGS_RELEASE="/MT /O2 /Ob2 /DNDEBUG"',
                '-DCMAKE_CXX_FLAGS_RELEASE="/MT /O2 /Ob2 /DNDEBUG"',
                '-DCMAKE_INSTALL_PREFIX=' . escapeshellarg($lib->getBuildRootPath()),
                '-B ' . escapeshellarg($buildDir),
            )
            ->toStep(1)
            ->build();

        // fix cmake_install.cmake install prefix (GmSSL overrides it internally)
        $installCmake = "{$buildDir}\\cmake_install.cmake";
        FileSystem::writeFile(
            $installCmake,
            'set(CMAKE_INSTALL_PREFIX "' . str_replace('\\', '/', $lib->getBuildRootPath()) . '")' . PHP_EOL . FileSystem::readFile($installCmake)
        );

        cmd()->cd($buildDir)->exec('nmake install XCFLAGS=/MT');
    }
}
