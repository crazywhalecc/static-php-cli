<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('zlib')]
class zlib
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)->exec("./configure --static --prefix={$lib->getBuildRootPath()}")->make();

        // Patch pkg-config file
        $lib->patchPkgconfPrefix(['zlib.pc'], PKGCONF_PATCH_PREFIX);
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        WindowsCMakeExecutor::create($lib)->build();
        $detect_list = [
            'zlibstatic.lib',
            'zs.lib',
            'libzs.lib',
        ];
        foreach ($detect_list as $item) {
            if (file_exists("{$lib->getLibDir()}\\{$item}")) {
                FileSystem::copy("{$lib->getLibDir()}\\{$item}", "{$lib->getLibDir()}\\zlib_a.lib");
                FileSystem::copy("{$lib->getLibDir()}\\{$item}", "{$lib->getLibDir()}\\zlibstatic.lib");
                break;
            }
        }
        FileSystem::removeFileIfExists("{$lib->getBinDir()}\\zlib.dll");
        FileSystem::removeFileIfExists("{$lib->getLibDir()}\\zlib.lib");
        FileSystem::removeFileIfExists("{$lib->getLibDir()}\\libz.dll");
        FileSystem::removeFileIfExists("{$lib->getLibDir()}\\libz.lib");
        FileSystem::removeFileIfExists("{$lib->getLibDir()}\\z.lib");
        FileSystem::removeFileIfExists("{$lib->getLibDir()}\\z.dll");
    }
}
