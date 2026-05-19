<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use Package\Artifact\llvm_tools;
use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;

class LlvmToolsCheck
{
    /** @noinspection PhpUnused */
    #[CheckItem('if llvm-tools (objcopy/strip/profdata) are built', limit_os: 'Linux', level: 798)]
    public function checkLlvmTools(): CheckResult
    {
        $binDir = PKG_ROOT_PATH . '/llvm-tools/bin';
        if (new llvm_tools()->allBuilt($binDir)) {
            return CheckResult::ok($binDir);
        }
        return CheckResult::fail('llvm-tools are not built', 'build-llvm-tools');
    }

    #[FixItem('build-llvm-tools')]
    public function fixLlvmTools(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('llvm-tools');
        $installer->run(true);
        new llvm_tools()->buildForHost();
        return new llvm_tools()->allBuilt(PKG_ROOT_PATH . '/llvm-tools/bin');
    }
}
