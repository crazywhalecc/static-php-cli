<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait xz
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-scripts',
                '--disable-doc',
                '--with-libiconv',
                '--bindir=/tmp/xz', // xz binary will corrupt `tar` command, that's really strange.
            )
            ->make();
        $this->patchPkgconfPrefix(['liblzma.pc']);
        $this->patchLaDependencyPrefix();
    }
}
