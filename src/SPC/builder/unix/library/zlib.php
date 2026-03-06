<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait zlib
{
    protected function build(): void
    {
        $static = getenv('SPC_LINK_STATIC') ? '--static' : '';
        UnixAutoconfExecutor::create($this)->exec("./configure {$static} --prefix={$this->getBuildRootPath()}")->make();
        $this->patchPkgconfPrefix(['zlib.pc']);
    }
}
