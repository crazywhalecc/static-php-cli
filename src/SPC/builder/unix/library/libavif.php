<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\executor\UnixCMakeExecutor;

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
    }
}
