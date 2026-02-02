<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;

#[Library('libssh2')]
class libssh2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->optionalPackage('zlib', ...cmake_boolean_args('ENABLE_ZLIB_COMPRESSION'))
            ->addConfigureArgs(
                '-DBUILD_EXAMPLES=OFF',
                '-DBUILD_TESTING=OFF'
            )
            ->build();

        $lib->patchPkgconfPrefix(['libssh2.pc']);
    }
}
