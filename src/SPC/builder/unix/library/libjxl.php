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
            ->addConfigureArgs('-DJPEGXL_ENABLE_TOOLS=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_EXAMPLES=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_MANPAGES=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_BENCHMARK=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_PLUGINS=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_SJPOEG=ON')
            ->addConfigureArgs('-DJPEGXL_ENABLE_JNI=OFF')
            ->addConfigureArgs('-DJPEGXL_ENABLE_TRANSCODE_JPEG=ON')
            ->addConfigureArgs('-DJPEGXL_STATIC=' . (SPCTarget::isStatic() ? 'ON' : 'OFF'))
            ->addConfigureArgs('-DJPEGXL_FORCE_SYSTEM_BROTLI=ON')
            ->addConfigureArgs('-DBUILD_TESTING=OFF')
            ->build();
    }
}
