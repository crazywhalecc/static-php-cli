<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('qdbm')]
class qdbm
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $ac = UnixAutoconfExecutor::create($lib)->configure();
        FileSystem::replaceFileRegex($lib->getSourceDir() . '/Makefile', '/MYLIBS = libqdbm.a.*/m', 'MYLIBS = libqdbm.a');
        $ac->make(SystemTarget::getTargetOS() === 'Darwin' ? 'mac' : '');
        $lib->patchPkgconfPrefix(['qdbm.pc']);
    }
}
