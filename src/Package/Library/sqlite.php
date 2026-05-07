<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('sqlite')]
class sqlite
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->configure()->make();
        $lib->patchPkgconfPrefix(['sqlite3.pc']);
    }

    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Windows', 'This patch is only for Windows builds.');
        FileSystem::copy(ROOT_DIR . '/src/globals/extra/Makefile-sqlite', "{$lib->getSourceDir()}\\Makefile");
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        cmd()->cd($lib->getSourceDir())
            ->exec("nmake PREFIX={$lib->getBuildRootPath()} install-static");
    }
}
