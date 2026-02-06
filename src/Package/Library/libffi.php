<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('libffi')]
class libffi extends LibraryPackage
{
    #[BuildFor('Linux')]
    public function buildLinux(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure()->make();

        if (is_file("{$this->getBuildRootPath()}/lib64/libffi.a")) {
            copy("{$this->getBuildRootPath()}/lib64/libffi.a", "{$this->getBuildRootPath()}/lib/libffi.a");
            unlink("{$this->getBuildRootPath()}/lib64/libffi.a");
        }
        $this->patchPkgconfPrefix(['libffi.pc']);
    }

    #[BuildFor('Darwin')]
    public function buildDarwin(): void
    {
        $arch = getenv('SPC_ARCH');
        UnixAutoconfExecutor::create($this)
            ->configure(
                "--host={$arch}-apple-darwin",
                "--target={$arch}-apple-darwin",
            )
            ->make();
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
