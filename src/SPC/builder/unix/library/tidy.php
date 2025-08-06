<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait tidy
{
    protected function build(): void
    {
        $cmake = UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/build-dir")
            ->addConfigureArgs(
                '-DSUPPORT_CONSOLE_APP=OFF',
                '-DBUILD_SHARED_LIB=OFF'
            );
        if (version_compare(get_cmake_version(), '4.0.0', '>=')) {
            $cmake->addConfigureArgs('-DCMAKE_POLICY_VERSION_MINIMUM=3.5');
        }
        $cmake->build();
        $this->patchPkgconfPrefix(['tidy.pc']);
    }
}
