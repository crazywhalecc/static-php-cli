<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait nghttp3
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure('--enable-lib-only')->make();
        $this->patchPkgconfPrefix(['libnghttp3.pc']);
        $this->patchLaDependencyPrefix();
    }
}
