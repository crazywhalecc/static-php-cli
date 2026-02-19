<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\SourcePatcher;

#[Extension('readline')]
class readline
{
    #[BeforeStage('php', [php::class, 'makeCliForUnix'], 'ext-readline')]
    #[PatchDescription('Fix readline static build with musl')]
    public function beforeMakeLinuxCli(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        if ($toolchain->isStatic()) {
            $php_src = $installer->getBuildPackage('php')->getSourceDir();
            SourcePatcher::patchFile('musl_static_readline.patch', $php_src);
        }
    }

    #[AfterStage('php', [php::class, 'makeCliForUnix'], 'ext-readline')]
    public function afterMakeLinuxCli(PackageInstaller $installer, ToolchainInterface $toolchain): void
    {
        if ($toolchain->isStatic()) {
            $php_src = $installer->getBuildPackage('php')->getSourceDir();
            SourcePatcher::patchFile('musl_static_readline.patch', $php_src, true);
        }
    }
}
