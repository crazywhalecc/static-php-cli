<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\executor\UnixCMakeExecutor;

trait libwebp
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DWEBP_BUILD_EXTRAS=ON')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(['libsharpyuv.pc', 'libwebp.pc', 'libwebpdecoder.pc', 'libwebpdemux.pc', 'libwebpmux.pc'], PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
        $this->patchPkgconfPrefix(['libwebp.pc'], PKGCONF_PATCH_CUSTOM, ['/-lwebp$/m', '-lwebp -lsharpyuv']);
    }
}
