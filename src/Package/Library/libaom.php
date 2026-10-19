<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\System\UnixUtil;

#[Library('libaom')]
class libaom extends LibraryPackage
{
    #[BuildFor('Windows')]
    public function buildWin(): void
    {
        WindowsCMakeExecutor::create($this)
            ->setBuildDir("{$this->getSourceDir()}/builddir")
            ->addConfigureArgs(
                '-DAOM_TARGET_CPU=generic',
                '-DENABLE_TESTS=OFF',
                '-DENABLE_EXAMPLES=OFF',
                '-DENABLE_TOOLS=OFF',
                '-DENABLE_DOCS=OFF',
            )
            ->build();
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(ToolchainInterface $toolchain): void
    {
        $extra = getenv('SPC_COMPILER_EXTRA');
        if ($toolchain instanceof ZigToolchain) {
            $new = trim($extra . ' -D_GNU_SOURCE');
            f_putenv("SPC_COMPILER_EXTRA={$new}");
        }
        $targetCpu = SystemTarget::getTargetArch();
        if (str_starts_with($targetCpu, 'aarch')) {
            $targetCpu = str_replace('aarch', 'arm', $targetCpu);
        }
        // On ARM, features like crc (used by av1/encoder/arm/hash_arm_crc32.c) are only
        // enabled when the compiler receives them via -march/-mcpu; zig-cc.sh rewrites
        // -march=armv8-a+crc to -mcpu=generic+v8a+crc so the flag survives the wrapper.
        if (
            in_array($targetCpu, ['x86', 'x86_64'], true)
            && !UnixUtil::findCommand('nasm')
            && !UnixUtil::findCommand('yasm')
        ) {
            $targetCpu = 'generic';
        }
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->getSourceDir()}/builddir")
            ->addConfigureArgs(
                "-DAOM_TARGET_CPU={$targetCpu}",
                '-DCONFIG_RUNTIME_CPU_DETECT=1',
                '-DENABLE_EXAMPLES=OFF',
                '-DENABLE_TESTS=OFF',
                '-DENABLE_TOOLS=OFF',
                '-DENABLE_DOCS=OFF',
            )
            ->build();
        f_putenv("SPC_COMPILER_EXTRA={$extra}");
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
