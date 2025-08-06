<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libwebp
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs('-DWEBP_BUILD_EXTRAS=ON')
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
    }
}
