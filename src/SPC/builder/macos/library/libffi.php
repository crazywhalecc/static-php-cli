<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\util\executor\UnixAutoconfExecutor;

class libffi extends MacOSLibraryBase
{
    public const NAME = 'libffi';

    protected function build(): void
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
