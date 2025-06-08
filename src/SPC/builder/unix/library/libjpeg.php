<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;

trait libjpeg
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DENABLE_STATIC=ON',
                '-DENABLE_SHARED=OFF',
            )
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libjpeg.pc', 'libturbojpeg.pc']);
        $this->cleanLaFiles();
    }
}
