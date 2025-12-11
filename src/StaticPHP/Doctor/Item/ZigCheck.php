<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;

#[OptionalCheck([self::class, 'optionalCheck'])]
class ZigCheck
{
    public static function optionalCheck(): bool
    {
        return ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain;
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if zig is installed', level: 800)]
    public function checkZig(): CheckResult
    {
        $installer = new PackageInstaller();
        $package = 'zig';
        $installer->addInstallPackage($package);
        $installed = $installer->isPackageInstalled($package);
        if ($installed) {
            return CheckResult::ok();
        }
        return CheckResult::fail('zig is not installed', 'install-zig');
    }

    #[FixItem('install-zig')]
    public function installZig(): bool
    {
        $installer = new PackageInstaller();
        $installer->addInstallPackage('zig');
        $installer->run(false);
        return $installer->isPackageInstalled('zig');
    }
}
