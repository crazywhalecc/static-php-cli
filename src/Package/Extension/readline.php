<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\SourcePatcher;

#[Extension('readline')]
class readline
{
    #[BeforeStage('php', 'unix-make-cli')]
    public function beforeMakeLinuxCli(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        if ($toolchain->isStatic()) {
            $php_src = $installer->getBuildPackage('php')->getSourceDir();
            SourcePatcher::patchFile('musl_static_readline.patch', $php_src);
        }
    }

    #[AfterStage('php', 'unix-make-cli')]
    public function afterMakeLinuxCli(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        if ($toolchain->isStatic()) {
            $php_src = $installer->getBuildPackage('php')->getSourceDir();
            SourcePatcher::patchFile('musl_static_readline.patch', $php_src, true);
        }
    }
}
