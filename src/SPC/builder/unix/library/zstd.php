<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait zstd
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/build/cmake/build")
            ->addConfigureArgs(
                '-DZSTD_BUILD_STATIC=ON', // otherwise zstd util fails to build
                '-DZSTD_BUILD_SHARED='. (getenv('SPC_STATIC_LIBS') ? 'OFF' : 'ON'),
            )
            ->build();
        $this->patchPkgconfPrefix();
    }
}
