<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\SystemUtil;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\executor\UnixCMakeExecutor;
use SPC\util\SPCTarget;

trait libaom
{
    protected function build(): void
    {
        $extra = getenv('SPC_COMPILER_EXTRA');
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            $new = trim($extra . ' -D_GNU_SOURCE');
            f_putenv("SPC_COMPILER_EXTRA={$new}");
        }
        $targetCpu = SPCTarget::getTargetArch();
        if (str_starts_with($targetCpu, 'aarch')) {
            $targetCpu = str_replace('aarch', 'arm', $targetCpu);
        }
        if (!SystemUtil::findCommand('nasm') && !SystemUtil::findCommand('yasm')) {
            $targetCpu = 'generic';
        }
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->source_dir}/builddir")
            ->addConfigureArgs(
                "-DAOM_TARGET_CPU={$targetCpu}",
                '-DCONFIG_RUNTIME_CPU_DETECT=1',
                '-DENABLE_EXAMPLES=0',
                '-DENABLE_TOOLS=0',
                '-DENABLE_TESTS=0',
                '-DENABLE_DOCS=0'
            )
            ->build();
        f_putenv("SPC_COMPILER_EXTRA={$extra}");
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
