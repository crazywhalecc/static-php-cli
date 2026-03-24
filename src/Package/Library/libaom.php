<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\Executor\WindowsCMakeExecutor;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;

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
        UnixCMakeExecutor::create($this)
            ->setBuildDir("{$this->getSourceDir()}/builddir")
            ->addConfigureArgs('-DAOM_TARGET_CPU=generic')
            ->build();
        f_putenv("SPC_COMPILER_EXTRA={$extra}");
        $this->patchPkgconfPrefix(['aom.pc']);
    }
}
