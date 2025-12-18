<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait libwebp
{
    protected function build(): void
    {
        $code = '#include <immintrin.h>
int main() { return _mm256_cvtsi256_si32(_mm256_setzero_si256()); }';
        $cc = getenv('CC') ?: 'gcc';
        [$ret] = shell()->execWithResult("printf '%s' '{$code}' | {$cc} -x c -mavx2 -o /dev/null - 2>&1");
        $disableAvx2 = $ret !== 0 && GNU_ARCH === 'x86_64' && PHP_OS_FAMILY === 'Linux';

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
                $disableAvx2 ? '-DWEBP_ENABLE_SIMD=OFF' : ''
            )
            ->build();
        // patch pkgconfig
        $this->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX | PKGCONF_PATCH_LIBDIR);
        $this->patchPkgconfPrefix(['libsharpyuv.pc'], PKGCONF_PATCH_CUSTOM, ['/^includedir=.*$/m', 'includedir=${prefix}/include/webp']);
    }
}
