<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait libde265
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DENABLE_SDL=OFF')
            ->build();

        if (PHP_OS_FAMILY === 'Linux') {
            $libheifpc = realpath(BUILD_LIB_PATH . '/pkgconfig/libde265.pc');
            FileSystem::replaceFileStr($libheifpc, '-lc++', '-lstdc++');
        }
        $this->patchPkgconfPrefix(['libde265.pc']);
    }
}
