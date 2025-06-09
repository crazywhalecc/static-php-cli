<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait zlib
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->exec("./configure --static --prefix={$this->getBuildRootPath()}")->make();
        $this->patchPkgconfPrefix(['zlib.pc']);
    }
}
