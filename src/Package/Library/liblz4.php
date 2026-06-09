<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('liblz4')]
class liblz4
{
    #[PatchBeforeBuild]
    #[PatchDescription('Compile lib sources individually so -flto -c with multiple inputs works under zig-cc/clang')]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        // `-flto -c` with multiple input files only writes a .o for the
        // first source — the others are silently dropped, leaving liblz4.a with a
        // single object. Compile each source individually so all .o files exist.
        FileSystem::replaceFileStr(
            $lib->getSourceDir() . '/lib/Makefile',
            "liblz4.a: \$(SRCFILES)\nifeq (\$(BUILD_STATIC),yes)  # can be disabled on command line\n\t@echo compiling static library\n\t\$(COMPILE.c) \$^\n\t\$(AR) rcs \$@ *.o\nendif",
            "liblz4.a: \$(SRCFILES:.c=.o)\nifeq (\$(BUILD_STATIC),yes)  # can be disabled on command line\n\t@echo compiling static library\n\t\$(AR) rcs \$@ \$^\nendif"
        );
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)
            ->setWorkingDir("{$lib->getSourceDir()}/build/cmake")
            ->setBuildDir("{$lib->getSourceDir()}/_win_build")
            ->addConfigureArgs('-DLZ4_BUILD_CLI=OFF')
            ->build();
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
