<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\executor\UnixCMakeExecutor;

trait libaom
{
    protected function build(): void
    {
        $extra = getenv('SPC_COMPILER_EXTRA');
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            $new = trim($extra . ' -D_GNU_SOURCE');
            f_putenv("SPC_COMPILER_EXTRA={$new}");
        }
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/builddir")
            ->addConfigureArgs('-DAOM_TARGET_CPU=generic')
            ->build();
        f_putenv("SPC_COMPILER_EXTRA={$extra}");
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
