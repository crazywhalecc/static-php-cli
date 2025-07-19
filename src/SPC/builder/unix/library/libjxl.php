<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

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
            ->addConfigureArgs('-DJPEGXL_ENABLE_SJPEG=OFF')
            ->addConfigureArgs('-DJPEGXL_STATIC=ON')
            ->addConfigureArgs('-DBUILD_TESTING=OFF')
            ->build();
    }
}
