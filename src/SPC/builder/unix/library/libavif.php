<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait libavif
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DAVIF_LIBYUV=OFF')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libavif.pc']);
        $this->cleanLaFiles();
    }
}
