<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixCMakeExecutor;

trait libuv
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DLIBUV_BUILD_SHARED=OFF')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libuv-static.pc']);
    }
}
