<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libwebp
{
    protected function build(): void
    {
        $cflags = getenv('SPC_DEFAULT_C_FLAGS') ?: getenv('CFLAGS') ?: '';
        $has_avx2 = str_contains($cflags, '-mavx2') || str_contains($cflags, '-march=x86-64-v2') || str_contains($cflags, '-march=x86-64-v3');
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DWEBP_BUILD_EXTRAS=ON',
                '-DWEBP_ENABLE_SIMD=' . ($has_avx2 ? 'ON' : 'OFF'),
            )
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
    }
}
