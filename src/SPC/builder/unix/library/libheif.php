<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait libheif
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '--preset=release',
                '-DWITH_EXAMPLES=OFF',
                '-DWITH_GDK_PIXBUF=OFF',
                '-DBUILD_TESTING=OFF',
                '-DWITH_LIBSHARPYUV=ON', // optional: libwebp
                '-DENABLE_PLUGIN_LOADING=OFF',
            )
            ->build();
        $this->patchPkgconfPrefix(['libheif.pc']);
    }
}
