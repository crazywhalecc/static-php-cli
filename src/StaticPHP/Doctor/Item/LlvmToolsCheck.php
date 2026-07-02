<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use Package\Artifact\llvm_tools;
use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;

#[OptionalCheck([self::class, 'optionalCheck'])]
class LlvmToolsCheck
{
    public static function optionalCheck(): bool
    {
        return ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain;
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if llvm-tools (objcopy/strip/profdata) are built', level: 798)]
    public function checkLlvmTools(): CheckResult
    {
        if (llvm_tools::isInstalled()) {
            return CheckResult::ok(llvm_tools::path() . '/bin');
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
        return llvm_tools::isInstalled();
    }
}
