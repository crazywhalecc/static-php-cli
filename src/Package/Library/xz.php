<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('xz')]
class xz
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure(
                '--disable-scripts',
                '--disable-doc',
                '--with-libiconv',
                '--bindir=/tmp/xz', // xz binary will corrupt `tar` command, that's really strange.
            )
            ->make();
        $lib->patchPkgconfPrefix(['liblzma.pc']);
        $lib->patchLaDependencyPrefix();
    }
}
