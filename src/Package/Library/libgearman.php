<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libgearman')]
class libgearman
{
    #[PatchBeforeBuild]
    #[PatchDescription('Skip the unconditional Boost probe; libgearman itself does not use Boost (only the server/CLI/benchmark targets we skip do)')]
    public function patchBeforeBuild(LibraryPackage $lib): bool
    {
        FileSystem::replaceFileStr(
            $lib->getSourceDir() . '/configure',
            'as_fn_error $? "could not find boost" "$LINENO" 5',
            ':',
        );
        return true;
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->appendEnv([
                'CFLAGS' => '-Wno-error',
                'CXXFLAGS' => '-std=c++17 -Wno-error',
            ])
            ->optionalPackage('libmemcached', '--enable-libmemcached', '--disable-libmemcached')
            ->removeConfigureArgs('--enable-pic')
            ->configure(
                '--disable-libdrizzle',
                '--disable-libtokyocabinet',
                '--disable-libpq',
                '--disable-jobserver',
                '--without-mysql',
            )
            // gearmand is a server+library project; we only need libgearman (client lib)
            ->make(
                target: 'configmake.h libhashkit-1.0/configure.h libgearman/error_code.hpp libgearman/command.hpp libtest/version.h libgearman/libgearman.la',
                with_install: 'install-libLTLIBRARIES install-nobase_includeHEADERS install-pkgconfigDATA',
            );
        $lib->patchPkgconfPrefix(['gearmand.pc']);
    }
}
