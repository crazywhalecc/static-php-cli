<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait libwebp
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DWEBP_BUILD_EXTRAS=OFF',
                '-DWEBP_BUILD_ANIM_UTILS=OFF',
                '-DWEBP_BUILD_CWEBP=OFF',
                '-DWEBP_BUILD_DWEBP=OFF',
                '-DWEBP_BUILD_GIF2WEBP=OFF',
                '-DWEBP_BUILD_IMG2WEBP=OFF',
                '-DWEBP_BUILD_VWEBP=OFF',
                '-DWEBP_BUILD_WEBPINFO=OFF',
                '-DWEBP_BUILD_WEBPMUX=OFF',
                '-DWEBP_BUILD_FUZZTEST=OFF',
                SPCTarget::getLibcVersion() === '2.31' && GNU_ARCH === 'x86_64' ? '-DWEBP_ENABLE_SIMD=OFF' : '' // fix an edge bug for debian 11 with gcc 10
            )
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
    }
}
