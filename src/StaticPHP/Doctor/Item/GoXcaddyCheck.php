<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;

#[OptionalCheck([self::class, 'optionalCheck'])]
class GoXcaddyCheck
{
    public static function optionalCheck(): bool
    {
        if (!ApplicationContext::has('craft')) {
            return false;
        }
        /** @var null|array $craft */
        $craft = ApplicationContext::get('craft');
        return in_array('frankenphp', $craft['sapi'] ?? [], true);
    }

    #[CheckItem('if go-xcaddy is installed', level: 800)]
    public function check(): CheckResult
    {
        if (!new PackageInstaller()->addInstallPackage('go-xcaddy')->isPackageInstalled('go-xcaddy')) {
            return CheckResult::fail('go-xcaddy is not installed', 'install-go-xcaddy');
        }
        return CheckResult::ok(PKG_ROOT_PATH . '/go-xcaddy/bin/xcaddy');
    }

    #[FixItem('install-go-xcaddy')]
    public function installGoXcaddy(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('go-xcaddy');
        $installer->run(true);
        return $installer->isPackageInstalled('go-xcaddy');
    }
}
