<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\PkgConfigUtil;

#[OptionalCheck([self::class, 'optionalCheck'])]
class PkgConfigCheck
{
    public static function optionalCheck(): bool
    {
        return SystemTarget::getTargetOS() !== 'Windows';
    }

    #[CheckItem('if pkg-config is installed or built', level: 800)]
    public function check(): CheckResult
    {
        if (!($pkgconf = PkgConfigUtil::findPkgConfig())) {
            return CheckResult::fail('pkg-config is not installed or built', 'install-pkg-config');
        }
        return CheckResult::ok($pkgconf);
    }

    #[CheckItem('if pkg-config is functional', level: 799)]
    public function checkFunctional(): CheckResult
    {
        $pkgconf = PkgConfigUtil::findPkgConfig();
        [$ret, $output] = shell()->execWithResult("{$pkgconf} --version", false);
        if ($ret !== 0) {
            return CheckResult::fail('pkg-config is not functional', 'install-pkg-config');
        }
        return CheckResult::ok(trim($output[0]));
    }

    #[FixItem('install-pkg-config')]
    public function fix(): bool
    {
        ApplicationContext::set('elephant', true);
        $installer = new PackageInstaller(['dl-binary-only' => true]);
        $installer->addInstallPackage('pkg-config');
        $installer->run(false, true);
        return true;
    }
}
