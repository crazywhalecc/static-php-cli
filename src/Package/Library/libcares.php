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

#[Library('libcares')]
class libcares
{
    #[PatchBeforeBuild]
    #[PatchDescription('Add missing dnsinfo.h for Apple platforms')]
    public function patchBeforeBuild(LibraryPackage $lib): bool
    {
        if (!file_exists("{$lib->getSourceDir()}/src/lib/thirdparty/apple/dnsinfo.h")) {
            FileSystem::createDir("{$lib->getSourceDir()}/src/lib/thirdparty/apple");
            copy(ROOT_DIR . '/src/globals/extra/libcares_dnsinfo.h', "{$lib->getSourceDir()}/src/lib/thirdparty/apple/dnsinfo.h");
            return true;
        }
        return false;
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure('--disable-tests')->make();

        $lib->patchPkgconfPrefix(['libcares.pc'], PKGCONF_PATCH_PREFIX);
    }
}
