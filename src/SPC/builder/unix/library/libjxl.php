<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait libjxl
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DJPEGXL_ENABLE_TOOLS=OFF',
                '-DJPEGXL_ENABLE_EXAMPLES=OFF',
                '-DJPEGXL_ENABLE_MANPAGES=OFF',
                '-DJPEGXL_ENABLE_BENCHMARK=OFF',
                '-DJPEGXL_ENABLE_PLUGINS=OFF',
                '-DJPEGXL_ENABLE_SJPOEG=ON',
                '-DJPEGXL_ENABLE_JNI=OFF',
                '-DJPEGXL_ENABLE_TRANSCODE_JPEG=ON',
                '-DJPEGXL_STATIC=' . (SPCTarget::isStatic() ? 'ON' : 'OFF'),
                '-DJPEGXL_FORCE_SYSTEM_BROTLI=ON',
                '-DBUILD_TESTING=OFF'
            )
            ->build();
    }
}
