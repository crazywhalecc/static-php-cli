<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SharedLibraryUtil;

#[Library('mimalloc')]
class mimalloc
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        $cmake = UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(
                SharedLibraryUtil::buildsShared($lib) ? '-DMI_BUILD_SHARED=ON' : '-DMI_BUILD_SHARED=OFF',
                '-DMI_BUILD_OBJECT=OFF',
                '-DMI_INSTALL_TOPLEVEL=ON',
            );
        if (SystemTarget::getLibc() === 'musl') {
            $cmake->addConfigureArgs('-DMI_LIBC_MUSL=ON');
        }
        $cmake->build();
    }
}
